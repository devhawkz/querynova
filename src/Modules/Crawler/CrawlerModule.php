<?php
/**
 * Technical crawler. A public request only enqueues a batch.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Core\Security\SsrfGuard;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Http\HttpClientInterface;
use QueryNova\Infrastructure\Http\WordPressHttpClient;
use QueryNova\Infrastructure\Lock\LockInterface;
use QueryNova\Infrastructure\Lock\TransientLock;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Crawler\Application\CrawlBatch;
use QueryNova\Modules\Crawler\Application\CrawlRun;
use QueryNova\Modules\Crawler\Application\CrawlWorker;
use QueryNova\Modules\Crawler\Infrastructure\CrawlPageStore;
use QueryNova\Modules\Crawler\Infrastructure\GuardedPageFetcher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlerModule extends AbstractModule {

    private ?CrawlBatch $batch = null;

    private ?CrawlWorker $worker = null;

    private ?JobRunner $runner = null;

    private ?FeatureRegistry $features = null;

    private ?WordPressEnvironment $environment = null;

    private ?SsrfGuard $guard = null;

    public function getName(): string {
        return 'crawler';
    }

    public function register( ContainerInterface $container ): void {
        $features    = $container->get( FeatureRegistry::class );
        $environment = $container->get( WordPressEnvironment::class );
        $http        = $container->get( WordPressHttpClient::class );
        $lock        = $container->get( TransientLock::class );
        $runner      = $container->get( JobRunner::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.crawl',
                    'Technical crawler',
                    'crawler',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::RUN_ANALYSIS ],
                )
            );
        }
        if ( ! $http instanceof HttpClientInterface || ! $lock instanceof LockInterface || ! $runner instanceof JobRunner ) {
            return;
        }
        $database          = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->guard       = new SsrfGuard();
        $this->batch       = new CrawlBatch( new GuardedPageFetcher( $this->guard, $http ) );
        $this->worker      = new CrawlWorker( $this->batch, new CrawlPageStore( $database ), $lock );
        $this->runner      = $runner;
        $this->features    = $features instanceof FeatureRegistry ? $features : null;
        $this->environment = $environment instanceof WordPressEnvironment ? $environment : null;
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $jobs->register(
            'querynova.crawl.batch',
            function ( array $payload, array $job ): void {
                unset( $job );
                $this->runBatch( $payload );
            }
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/crawl', [ $this, 'start' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/crawl', [ $this, 'show' ], Capability::RUN_ANALYSIS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function start( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->batch instanceof CrawlBatch || ! $this->runner instanceof JobRunner || ! $this->guard instanceof SsrfGuard ) {
            return new \WP_Error( 'querynova_crawl_unavailable', 'The crawler is unavailable.', [ 'status' => 500 ] );
        }
        if ( ! $this->enabled() ) {
            return new \WP_Error( 'querynova_crawl_disabled', 'The crawler is turned off.', [ 'status' => 403 ] );
        }
        $params     = $request->get_json_params();
        $url        = (string) ( $params['url'] ?? '' );
        $rawSitemap = $params['sitemap'] ?? null;
        $listed     = [];
        if ( is_array( $rawSitemap ) ) {
            foreach ( $rawSitemap as $item ) {
                if ( is_string( $item ) ) {
                    $listed[] = $item;
                }
            }
        }
        try {
            $this->guard->assertSafe( $url );
            $run = $this->batch->start( $url, $listed );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_crawl', $exception->getMessage(), [ 'status' => 400 ] );
        }
        $jobId = $this->runner->enqueue(
            'querynova.crawl.batch',
            $run->toPayload(),
            'crawl-batch-' . $run->runId() . '-0'
        );

        return [
            'job_id' => $jobId,
            'run_id' => $run->runId(),
            'status' => 'queued',
        ];
    }

    /**
     * Stored summary only. This does not fetch the site.
     *
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        unset( $request );
        $stored = get_option( CrawlPageStore::SUMMARY, null );
        if ( ! is_array( $stored ) ) {
            return [
                'status'        => 'unavailable',
                'pages_crawled' => null,
                'issue_count'   => null,
            ];
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function runBatch( array $payload ): void {
        if ( ! $this->worker instanceof CrawlWorker || ! $this->runner instanceof JobRunner ) {
            return;
        }
        $this->worker->handle(
            $payload,
            function ( CrawlRun $next ): void {
                if ( ! $this->runner instanceof JobRunner ) {
                    return;
                }
                $this->runner->enqueue(
                    'querynova.crawl.batch',
                    $next->toPayload(),
                    'crawl-batch-' . $next->runId() . '-' . count( $next->pages() )
                );
            }
        );
    }

    private function enabled(): bool {
        if ( ! $this->features instanceof FeatureRegistry || ! $this->environment instanceof WordPressEnvironment ) {
            return false;
        }

        return $this->features->isEnabled( 'querynova.crawl', $this->environment );
    }
}
