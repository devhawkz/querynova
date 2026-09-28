<?php
/**
 * SERP snapshots. A public request only enqueues a provider job.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp;

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
use QueryNova\Modules\Serp\Application\IndexAvailability;
use QueryNova\Modules\Serp\Application\RankTracker;
use QueryNova\Modules\Serp\Application\SerpCapture;
use QueryNova\Modules\Serp\Domain\SerpProvider;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Infrastructure\NullSerpProvider;
use QueryNova\Modules\Serp\Infrastructure\SerpRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpModule extends AbstractModule {

    private ?SerpCapture $capture = null;

    private ?SerpRepository $snapshots = null;

    private ?JobRunner $runner = null;

    private ?LockInterface $lock = null;

    private SerpProvider $provider;

    public function __construct() {
        $this->provider = new NullSerpProvider();
    }

    public function getName(): string {
        return 'serp';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.serp',
                    'SERP intelligence',
                    'serp',
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
        $database        = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->snapshots = new SerpRepository( $database );
        $this->capture   = new SerpCapture( $this->snapshots );
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $jobs->register(
            'querynova.serp.snapshot',
            function ( array $payload, array $job ): void {
                unset( $job );
                $this->run( $payload );
            }
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/serp', [ $this, 'enqueue' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/serp', [ $this, 'history' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/serp/keywords', [ $this, 'keywords' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/serp/keywords', [ $this, 'addKeyword' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/serp/keywords/bulk', [ $this, 'addKeywords' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/serp/keywords/csv', [ $this, 'importKeywords' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/serp/index-status', [ $this, 'indexStatus' ], Capability::RUN_ANALYSIS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function enqueue( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_serp_unavailable', 'SERP snapshots are unavailable.', [ 'status' => 500 ] );
        }
        $params = $request->get_json_params();
        try {
            $query = SerpQuery::fromArray( $params );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_serp', $exception->getMessage(), [ 'status' => 400 ] );
        }
        $jobId = $this->runner->enqueue(
            'querynova.serp.snapshot',
            $query->toArray(),
            'serp-' . bin2hex( random_bytes( 8 ) )
        );

        return [
            'job_id' => $jobId,
            'status' => 'queued',
        ];
    }

    /**
     * Stored snapshots only. This does not call a provider.
     *
     * @return array<string, mixed>
     */
    public function history( \WP_REST_Request $request ): array {
        $rows = $this->repository()->history( (int) $request->get_param( 'keyword_id' ) );
        if ( $rows === [] ) {
            return [
                'status'    => 'unavailable',
                'snapshots' => [],
            ];
        }

        return [
            'status'    => 'stored',
            'snapshots' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function keywords( \WP_REST_Request $request ): array {
        unset( $request );
        $catalog            = RankTracker::catalog();
        $catalog['history'] = RankTracker::history( $this->repository()->recentRanks( 20 ) );

        return $catalog;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function addKeyword( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        $result = RankTracker::add( is_array( $params ) ? $params : [] );
        if ( ( $result['status'] ?? '' ) === 'invalid' ) {
            return new \WP_Error( 'querynova_invalid_keyword', (string) $result['note'], [ 'status' => 400 ] );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function addKeywords( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $rows   = is_array( $params ) && is_array( $params['keywords'] ?? null ) ? $params['keywords'] : [];
        $clean  = [];
        foreach ( $rows as $row ) {
            if ( is_array( $row ) ) {
                $clean[] = $row;
            }
        }

        return RankTracker::addMany( $clean );
    }

    /**
     * @return array<string, mixed>
     */
    public function importKeywords( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $csv    = is_array( $params ) && is_string( $params['csv'] ?? null ) ? $params['csv'] : '';

        return RankTracker::importCsv( $csv );
    }

    /**
     * Index status and trends stay unavailable until a provider supplies rows.
     *
     * @return array<string, mixed>
     */
    public function indexStatus( \WP_REST_Request $request ): array {
        unset( $request );

        return [
            'index_status' => IndexAvailability::report( '', null, 'Index status' ),
            'trends'       => IndexAvailability::report( '', null, 'Trends' ),
            'note'         => 'No SERP vendor is selected.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function run( array $payload ): void {
        $lock = $this->lock instanceof LockInterface ? $this->lock : new TransientLock();
        if ( ! $lock->acquire( 'serp-batch', 120 ) ) {
            throw new JobException( 'A SERP snapshot is already running.' );
        }
        try {
            $this->worker()->capture( SerpQuery::fromArray( $payload ), $this->provider );
        } finally {
            $lock->release( 'serp-batch' );
        }
    }

    private function worker(): SerpCapture {
        return $this->capture instanceof SerpCapture ? $this->capture : new SerpCapture( $this->repository() );
    }

    private function repository(): SerpRepository {
        return $this->snapshots instanceof SerpRepository ? $this->snapshots : new SerpRepository( new ArrayDatabase() );
    }
}
