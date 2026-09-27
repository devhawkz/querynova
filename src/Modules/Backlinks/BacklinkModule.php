<?php
/**
 * Backlink snapshots. A public request only enqueues a provider job.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\JobException;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Lock\LockInterface;
use QueryNova\Infrastructure\Lock\TransientLock;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Backlinks\Application\BacklinkGap;
use QueryNova\Modules\Backlinks\Application\BacklinkSummary;
use QueryNova\Modules\Backlinks\Domain\BacklinkProvider;
use QueryNova\Modules\Backlinks\Infrastructure\BacklinkRepository;
use QueryNova\Modules\Backlinks\Infrastructure\NullBacklinkProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BacklinkModule extends AbstractModule {

    private ?BacklinkRepository $links = null;

    private ?JobRunner $runner = null;

    private ?LockInterface $lock = null;

    private BacklinkProvider $provider;

    private BacklinkSummary $summary;

    public function __construct() {
        $this->provider = new NullBacklinkProvider();
        $this->summary  = new BacklinkSummary();
    }

    public function getName(): string {
        return 'backlinks';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.backlinks',
                    'Backlinks',
                    'backlinks',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::RUN_ANALYSIS ],
                )
            );
        }
        $runner = $container->get( JobRunner::class );
        $lock   = $container->get( TransientLock::class );
        if ( $runner instanceof JobRunner ) {
            $this->runner = $runner;
        }
        if ( $lock instanceof LockInterface ) {
            $this->lock = $lock;
        }
        $database    = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->links = new BacklinkRepository( $database );
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $jobs->register(
            'querynova.backlink.snapshot',
            function ( array $payload, array $job ): void {
                unset( $job );
                $this->run( (string) ( $payload['target'] ?? '' ) );
            }
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/backlinks', [ $this, 'enqueue' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/backlinks', [ $this, 'show' ], Capability::RUN_ANALYSIS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function enqueue( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_backlinks_unavailable', 'Backlinks are unavailable.', [ 'status' => 500 ] );
        }
        $params = $request->get_json_params();
        $target = trim( (string) ( $params['target'] ?? '' ) );
        if ( $target === '' ) {
            return new \WP_Error( 'querynova_invalid_backlink', 'A target URL is required.', [ 'status' => 400 ] );
        }
        $jobId = $this->runner->enqueue( 'querynova.backlink.snapshot', [ 'target' => $target ], 'backlink-' . hash( 'sha256', $target . microtime( true ) ) );

        return [
            'job_id' => $jobId,
            'status' => 'queued',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $target = trim( (string) $request->get_param( 'target' ) );
        if ( $target === '' || ! $this->repository()->hasSnapshot( $target, $this->provider->id() ) ) {
            return $this->summary->summarize( null, null, $this->provider->authorityMetric() );
        }

        return [
            'status' => 'stored',
            'rows'   => $this->repository()->forTarget( $target ),
        ];
    }

    /**
     * @param list<\QueryNova\Modules\Backlinks\Domain\Backlink>|null $links
     * @param list<string>|null                                       $previous
     * @return array<string, mixed>
     */
    public function store( string $target, BacklinkProvider $provider, ?array $links, ?array $previous ): array {
        if ( $links === null ) {
            return $this->summary->summarize( null, null, $provider->authorityMetric() );
        }
        $this->repository()->replace( $target, $provider->id(), $links );

        return $this->summary->summarize( $links, $previous, $provider->authorityMetric() );
    }

    private function run( string $target ): void {
        if ( $target === '' ) {
            throw new ValidationException( 'A target URL is required.' );
        }
        $lock = $this->lock instanceof LockInterface ? $this->lock : new TransientLock();
        if ( ! $lock->acquire( 'backlink-batch', 120 ) ) {
            throw new JobException( 'A backlink snapshot is already running.' );
        }
        try {
            $previous = $this->repository()->hasSnapshot( $target, $this->provider->id() ) ? $this->repository()->sourceHashes( $target, $this->provider->id() ) : null;
            $this->store( $target, $this->provider, $this->provider->links( $target ), $previous );
        } finally {
            $lock->release( 'backlink-batch' );
        }
    }

    private function repository(): BacklinkRepository {
        return $this->links instanceof BacklinkRepository ? $this->links : new BacklinkRepository( new ArrayDatabase() );
    }
}
