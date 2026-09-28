<?php
/**
 * Analytics sync. A public request only enqueues. Providers are not called on the storefront.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics;

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
use QueryNova\Modules\Analytics\Application\AnalyticsScreens;
use QueryNova\Modules\Analytics\Application\AnalyticsWorkspace;
use QueryNova\Modules\Analytics\Application\ProviderConnections;
use QueryNova\Modules\Analytics\Domain\AnalyticsProvider;
use QueryNova\Modules\Analytics\Domain\CommerceMetricsProvider;
use QueryNova\Modules\Analytics\Domain\SearchConsoleProvider;
use QueryNova\Modules\Analytics\Infrastructure\AnalyticsRepository;
use QueryNova\Modules\Analytics\Infrastructure\NullAnalyticsProvider;
use QueryNova\Modules\Analytics\Infrastructure\NullCommerceMetricsProvider;
use QueryNova\Modules\Analytics\Infrastructure\NullSearchConsoleProvider;
use QueryNova\Modules\Experience\Application\ExperienceSummary;
use QueryNova\Modules\Serp\Application\IndexAvailability;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AnalyticsModule extends AbstractModule {

    private ?AnalyticsRepository $store = null;

    private ?JobRunner $runner = null;

    private ?LockInterface $lock = null;

    private SearchConsoleProvider $search;

    private AnalyticsProvider $analytics;

    private CommerceMetricsProvider $commerce;

    private AnalyticsWorkspace $workspace;

    public function __construct( ?SearchConsoleProvider $search = null, ?AnalyticsProvider $analytics = null, ?CommerceMetricsProvider $commerce = null ) {
        $this->search    = $search ?? new NullSearchConsoleProvider();
        $this->analytics = $analytics ?? new NullAnalyticsProvider();
        $this->commerce  = $commerce ?? new NullCommerceMetricsProvider();
        $this->workspace = new AnalyticsWorkspace();
    }

    public function getName(): string {
        return 'analytics';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.analytics',
                    'Analytics',
                    'analytics',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::VIEW_ANALYTICS ],
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
        $this->store = new AnalyticsRepository( $database );
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $jobs->register(
            'querynova.analytics.sync',
            function ( array $payload, array $job ): void {
                unset( $job );
                $this->run(
                    (string) ( $payload['property'] ?? '' ),
                    (string) ( $payload['start'] ?? '' ),
                    (string) ( $payload['end'] ?? '' )
                );
            }
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/analytics/sync', [ $this, 'enqueue' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/analytics', [ $this, 'show' ], Capability::VIEW_ANALYTICS );
        $rest->route( 'GET', '/analytics/connections', [ $this, 'connections' ], Capability::VIEW_ANALYTICS );
        $rest->route( 'POST', '/analytics/connections', [ $this, 'connectionAction' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/analytics/screens', [ $this, 'screens' ], Capability::VIEW_ANALYTICS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function enqueue( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_analytics_unavailable', 'Analytics sync is unavailable.', [ 'status' => 500 ] );
        }
        $params   = $request->get_json_params();
        $property = trim( (string) ( $params['property'] ?? '' ) );
        $start    = trim( (string) ( $params['start'] ?? '' ) );
        $end      = trim( (string) ( $params['end'] ?? '' ) );
        if ( $property === '' || $start === '' || $end === '' ) {
            return new \WP_Error( 'querynova_invalid_analytics', 'Property, start, and end are required.', [ 'status' => 400 ] );
        }
        $jobId = $this->runner->enqueue(
            'querynova.analytics.sync',
            [
                'property' => $property,
                'start'    => $start,
                'end'      => $end,
            ],
            'analytics-' . hash( 'sha256', $property . $start . $end . microtime( true ) )
        );

        return [
            'job_id' => $jobId,
            'status' => 'queued',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        unset( $request );
        if ( ! $this->repository()->hasSearch() ) {
            return $this->workspace->search( null );
        }

        return [
            'status' => 'stored',
            'note'   => 'Stored provider rows. This response did not call a provider.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect( string $property, string $start, string $end ): array {
        $search   = $this->search->rows( $property, $start, $end );
        $traffic  = $this->analytics->rows( $property, $start, $end );
        $commerce = $this->commerce->rows( $start, $end );

        return [
            'fetched_on_frontend'  => false,
            'search'               => $this->workspace->search( $search ),
            'stored_search_rows'   => $search === null ? 0 : $this->repository()->saveSearch( $property, $search ),
            'stored_traffic_rows'  => $traffic === null ? 0 : $this->repository()->saveTraffic( $traffic ),
            'stored_commerce_rows' => $commerce === null ? 0 : $this->repository()->saveCommerce( $commerce ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function connections( \WP_REST_Request $request ): array {
        unset( $request );

        return [
            'search_console' => ProviderConnections::card( 'search_console', $this->search->id() ),
            'ga4'            => ProviderConnections::card( 'ga4', $this->analytics->id() ),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function connectionAction( \WP_REST_Request $request ): array|\WP_Error {
        $params   = $request->get_json_params();
        $params   = is_array( $params ) ? $params : [];
        $channel  = is_string( $params['channel'] ?? null ) ? $params['channel'] : '';
        $action   = is_string( $params['action'] ?? null ) ? $params['action'] : '';
        $property = is_string( $params['property'] ?? null ) ? $params['property'] : '';
        $start    = is_string( $params['start'] ?? null ) ? $params['start'] : '';
        $end      = is_string( $params['end'] ?? null ) ? $params['end'] : '';
        if ( ! in_array( $channel, ProviderConnections::CHANNELS, true ) ) {
            return new \WP_Error( 'querynova_invalid_connection', 'Choose Search Console or GA4.', [ 'status' => 400 ] );
        }
        $providerId = $channel === 'search_console' ? $this->search->id() : $this->analytics->id();
        if ( $action === 'connect' ) {
            return ProviderConnections::connect( $channel, $property, $providerId );
        }
        if ( $action === 'disconnect' ) {
            return ProviderConnections::disconnect( $channel );
        }
        if ( $action === 'test' ) {
            return $channel === 'search_console'
                ? ProviderConnections::probeSearch( $this->search, $property, $start, $end )
                : ProviderConnections::probeAnalytics( $this->analytics, $property, $start, $end );
        }
        if ( $action === 'resync' ) {
            return $this->queueResync( $channel, $providerId, $start, $end );
        }

        return new \WP_Error( 'querynova_invalid_connection', 'Choose connect, test, disconnect, or resync.', [ 'status' => 400 ] );
    }

    /**
     * Stored screen model. This does not call a provider.
     *
     * @return array<string, mixed>
     */
    public function screens( \WP_REST_Request $request ): array {
        $start = trim( (string) $request->get_param( 'start' ) );
        $end   = trim( (string) $request->get_param( 'end' ) );

        return self::screenModel( $this->search->id(), $this->analytics->id(), $start, $end );
    }

    /**
     * @return array<string, mixed>
     */
    public static function screenModel( string $searchId, string $analyticsId, string $start, string $end ): array {
        if ( $start === '' || $end === '' ) {
            $day   = 86400;
            $end   = gmdate( 'Y-m-d', time() - $day );
            $start = gmdate( 'Y-m-d', time() - ( 28 * $day ) );
        }

        return AnalyticsScreens::present(
            ProviderConnections::card( 'search_console', $searchId ),
            ProviderConnections::card( 'ga4', $analyticsId ),
            $start,
            $end,
            null,
            null,
            null,
            null,
            ( new ExperienceSummary() )->summarize( null, 'mobile' ),
            IndexAvailability::report( '', null, 'Index status' ),
            IndexAvailability::report( '', null, 'Trends' )
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function queueResync( string $channel, string $providerId, string $start, string $end ): array|\WP_Error {
        $plan = ProviderConnections::resyncPlan( $channel, $providerId, $start, $end );
        if ( $plan['queued'] !== true ) {
            return $plan;
        }
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_analytics_unavailable', 'Analytics sync is unavailable.', [ 'status' => 500 ] );
        }
        $payload = is_array( $plan['payload'] ?? null ) ? $plan['payload'] : [];
        unset( $payload['url'], $payload['urls'] );
        $jobId = $this->runner->enqueue(
            (string) $plan['type'],
            $payload,
            'analytics-resync-' . bin2hex( random_bytes( 8 ) )
        );

        return [
            'job_id' => $jobId,
            'status' => 'queued',
            'queued' => true,
            'note'   => $plan['note'],
        ];
    }

    private function run( string $property, string $start, string $end ): void {
        if ( $property === '' || $start === '' || $end === '' ) {
            throw new ValidationException( 'Property, start, and end are required.' );
        }
        $lock = $this->lock instanceof LockInterface ? $this->lock : new TransientLock();
        if ( ! $lock->acquire( 'analytics-sync', 120 ) ) {
            throw new JobException( 'An analytics sync is already running.' );
        }
        try {
            $this->collect( $property, $start, $end );
        } finally {
            $lock->release( 'analytics-sync' );
        }
    }

    private function repository(): AnalyticsRepository {
        return $this->store instanceof AnalyticsRepository ? $this->store : new AnalyticsRepository( new ArrayDatabase() );
    }
}
