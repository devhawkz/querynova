<?php
/**
 * WP-CLI command behavior. Crawl and analytics sync are queued, not run here.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cli;

use QueryNova\Core\BuildChannel;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Health\HealthRegistry;
use QueryNova\Core\Modules\ModuleRegistry;
use QueryNova\Infrastructure\Cache\CacheInterface;
use QueryNova\Infrastructure\Database\LogRepository;
use QueryNova\Infrastructure\Database\MigrationManager;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Queue\JobStatus;
use QueryNova\Modules\Crawler\Application\CrawlBatch;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CliCommands {

    public function __construct(
        private readonly string $version,
        private readonly string $environment,
        private readonly ?string $wpVersion,
        private readonly ?string $wooVersion,
        private readonly MigrationManager $migrations,
        private readonly HealthRegistry $health,
        private readonly ModuleRegistry $modules,
        private readonly JobRepository $jobs,
        private readonly JobRunner $runner,
        private readonly CacheInterface $cache,
        private readonly LogRepository $logs,
        private readonly ?bool $cronScheduled,
    ) {
    }

    /**
     * @param list<string>         $words
     * @param array<string, mixed> $flags
     * @return array<string, mixed>
     */
    public function execute( array $words, array $flags = [] ): array {
        $command = implode( ' ', $words );

        return match ( $command ) {
            'status' => $this->status(),
            'health' => $this->health(),
            'modules' => $this->modules(),
            'migrate' => $this->migrate(),
            'jobs list' => $this->jobsList(),
            'jobs retry' => $this->jobsRetry( (int) ( $flags['id'] ?? 0 ) ),
            'cache clear' => $this->cacheClear(),
            'crawl run' => $this->crawlRun( (string) ( $flags['url'] ?? '' ) ),
            'analytics sync' => $this->analyticsSync(
                (string) ( $flags['property'] ?? '' ),
                (string) ( $flags['start'] ?? '' ),
                (string) ( $flags['end'] ?? '' )
            ),
            'diagnostics' => $this->diagnostics(),
            default => [
                'ok'    => false,
                'error' => 'Unknown command.',
            ],
        };
    }

    /**
     * @param array<string, mixed> $result
     */
    public function render( array $result, bool $json ): string {
        if ( $json ) {
            $encoded = wp_json_encode( $result );

            return is_string( $encoded ) ? $encoded : '{}';
        }
        if ( ( $result['ok'] ?? true ) === false ) {
            return (string) ( $result['error'] ?? 'Command failed.' );
        }
        $lines = [];
        foreach ( $result as $key => $value ) {
            if ( is_scalar( $value ) || $value === null ) {
                $lines[] = $key . ': ' . ( $value === null ? 'unavailable' : (string) $value );
            }
        }

        return $lines === [] ? 'ok' : implode( "\n", $lines );
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array {
        return [
            'ok'             => true,
            'name'           => 'QueryNova',
            'version'        => $this->version,
            'schema_version' => $this->migrations->currentVersion(),
            'environment'    => $this->environment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array {
        $checks = [];
        foreach ( $this->health->run() as $report ) {
            $checks[] = $report->toArray();
        }

        return [
            'ok'     => true,
            'status' => $this->health->overall()->value,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function modules(): array {
        $rows = [];
        foreach ( $this->modules->all() as $module ) {
            $rows[] = [
                'name'     => $module->getName(),
                'optional' => $module->isOptional(),
            ];
        }

        return [
            'ok'      => true,
            'modules' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function migrate(): array {
        try {
            $applied = $this->migrations->migrate();
        } catch ( \Throwable $exception ) {
            return [
                'ok'      => false,
                'error'   => $exception->getMessage(),
                'applied' => [],
            ];
        }

        return [
            'ok'      => true,
            'applied' => $applied,
            'current' => $this->migrations->currentVersion(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jobsList(): array {
        $rows = [];
        foreach ( $this->jobs->list( '', 20, 0 ) as $job ) {
            $rows[] = [
                'id'      => (int) ( $job['id'] ?? 0 ),
                'type'    => (string) ( $job['job_type'] ?? '' ),
                'status'  => (string) ( $job['status'] ?? '' ),
                'attempt' => (int) ( $job['attempt'] ?? 0 ),
            ];
        }

        return [
            'ok'   => true,
            'jobs' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jobsRetry( int $id ): array {
        $now     = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        $targets = [];
        if ( $id > 0 ) {
            $targets[] = $id;
        } else {
            foreach ( [ JobStatus::Failed->value, JobStatus::Dead->value, JobStatus::Retrying->value ] as $status ) {
                foreach ( $this->jobs->list( $status, 20, 0 ) as $job ) {
                    $targets[] = (int) ( $job['id'] ?? 0 );
                }
            }
        }
        $requeued = [];
        foreach ( $targets as $jobId ) {
            if ( $jobId > 0 && $this->jobs->requeue( $jobId, $now ) ) {
                $requeued[] = $jobId;
            }
        }

        return [
            'ok'       => true,
            'requeued' => $requeued,
            'note'     => 'Requeued jobs were not run by this command.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cacheClear(): array {
        $this->cache->flushGroup();

        return [
            'ok'   => true,
            'note' => 'The QueryNova cache group was cleared.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function crawlRun( string $url ): array {
        try {
            $run = ( new CrawlBatch( new ClosedPageFetcher() ) )->start( $url );
        } catch ( ValidationException $exception ) {
            return [
                'ok'     => false,
                'error'  => $exception->getMessage(),
                'status' => null,
            ];
        }
        $jobId = $this->runner->enqueue(
            'querynova.crawl.batch',
            $run->toPayload(),
            'crawl-batch-' . $run->runId() . '-0'
        );

        return [
            'ok'     => true,
            'status' => 'queued',
            'job_id' => $jobId,
            'note'   => 'The crawl was queued. This command did not fetch the site.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticsSync( string $property, string $start, string $end ): array {
        $property = trim( $property );
        $start    = trim( $start );
        $end      = trim( $end );
        if ( $property === '' || $start === '' || $end === '' ) {
            return [
                'ok'     => false,
                'error'  => 'Property, start, and end are required.',
                'status' => null,
            ];
        }
        $jobId = $this->runner->enqueue(
            'querynova.analytics.sync',
            [
                'property' => $property,
                'start'    => $start,
                'end'      => $end,
            ],
            'analytics-' . hash( 'sha256', $property . '|' . $start . '|' . $end )
        );

        return [
            'ok'     => true,
            'status' => 'queued',
            'job_id' => $jobId,
            'note'   => 'Analytics sync was queued. This command did not call a provider.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array {
        $names = [];
        foreach ( $this->modules->all() as $module ) {
            $names[] = $module->getName();
        }
        $errors       = $this->logs->search( [ 'level' => 'error' ], 10, 0 );
        $report       = DiagnosticsReport::build(
            [
                'environment'         => $this->environment,
                'querynova_build'     => BuildChannel::installedChannel(),
                'querynova_version'   => $this->version,
                'wp_version'          => $this->wpVersion,
                'php_version'         => PHP_VERSION,
                'woocommerce_version' => $this->wooVersion,
                'schema_version'      => $this->migrations->currentVersion(),
                'modules'             => $names,
                'queue'               => $this->jobs->statusCounts(),
                'cron_scheduled'      => $this->cronScheduled,
                'cache_adapter'       => 'object-cache',
                'pending_migrations'  => $this->migrations->pendingVersions(),
                'errors'              => $errors,
            ]
        );
        $report['ok'] = true;

        return $report;
    }
}
