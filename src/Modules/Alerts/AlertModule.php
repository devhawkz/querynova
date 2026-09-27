<?php
/**
 * Alert evaluation from supplied evidence. It does not fetch analytics.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Alerts;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Alerts\Application\AlertEvaluator;
use QueryNova\Modules\Alerts\Infrastructure\AlertRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AlertModule extends AbstractModule {

    private AlertEvaluator $evaluator;

    private ?AlertRepository $store = null;

    public function __construct( ?AlertRepository $store = null ) {
        $this->evaluator = new AlertEvaluator();
        $this->store     = $store;
    }

    public function getName(): string {
        return 'alerts';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.alerts',
                    'Alerts',
                    'alerts',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::VIEW_ANALYTICS ],
                )
            );
        }
        if ( ! $this->store instanceof AlertRepository ) {
            $database    = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
            $this->store = new AlertRepository( $database );
        }
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/alerts/evaluate', [ $this, 'create' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/alerts', [ $this, 'show' ], Capability::VIEW_ANALYTICS );
    }

    /**
     * @return array<string, mixed>
     */
    public function create( \WP_REST_Request $request ): array {
        return $this->evaluate( $request->get_json_params() );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        unset( $request );
        $count = $this->repository()->openCount();
        if ( $count === 0 ) {
            return [
                'status' => 'unavailable',
                'open'   => null,
                'note'   => 'No open alerts are stored.',
            ];
        }

        return [
            'status' => 'stored',
            'open'   => $count,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function evaluate( array $input ): array {
        $alerts = $this->evaluator->evaluate( $input );
        $object = trim( (string) ( $input['object'] ?? 'site' ) );
        $stored = $this->repository()->store( $object === '' ? 'site' : $object, $alerts );

        return [
            'alerts' => $alerts,
            'stored' => $stored,
            'note'   => 'Missing ranks, revenue, and traffic do not create alerts.',
        ];
    }

    private function repository(): AlertRepository {
        if ( ! $this->store instanceof AlertRepository ) {
            $this->store = new AlertRepository( new ArrayDatabase() );
        }

        return $this->store;
    }
}
