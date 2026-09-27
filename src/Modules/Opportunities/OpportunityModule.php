<?php
/**
 * Opportunity review. The request evaluates supplied inputs and does not apply changes.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Opportunities;

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
use QueryNova\Modules\Opportunities\Application\OpportunityEngine;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OpportunityModule extends AbstractModule {

    private OpportunityEngine $engine;

    private ?RecommendationRepository $store = null;

    public function __construct() {
        $this->engine = new OpportunityEngine();
    }

    public function getName(): string {
        return 'opportunities';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.opportunities',
                    'Opportunities',
                    'opportunities',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::RUN_ANALYSIS ],
                )
            );
        }
        $database    = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->store = new RecommendationRepository( $database );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/opportunities', [ $this, 'create' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/opportunities', [ $this, 'show' ], Capability::RUN_ANALYSIS );
    }

    /**
     * @return array<string, mixed>
     */
    public function create( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return $this->inspect( $params );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        unset( $request );
        $rows = $this->repository()->today();
        if ( $rows === [] ) {
            return [
                'status'  => 'unavailable',
                'actions' => null,
                'note'    => 'No stored actions yet.',
            ];
        }

        return [
            'status'  => 'stored',
            'actions' => $rows,
            'note'    => 'Suggested actions only. None of these rows were applied.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function inspect( array $input ): array {
        $report           = $this->engine->evaluate( $input );
        $report['decay']  = $this->engine->decay( $input );
        $report['ctr']    = $this->engine->ctr( $input );
        $recommendations  = $report['recommendations'];
        $report['stored'] = is_array( $recommendations ) ? $this->repository()->save( $recommendations ) : 0;

        return $report;
    }

    private function repository(): RecommendationRepository {
        return $this->store instanceof RecommendationRepository ? $this->store : new RecommendationRepository( new ArrayDatabase() );
    }
}
