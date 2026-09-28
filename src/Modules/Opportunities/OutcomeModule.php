<?php
/**
 * Records whether a suggestion was accepted, marked applied, and later measured.
 *
 * Marking a recommendation applied does not change the page.
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
use QueryNova\Modules\Experiments\Application\ExperimentComparison;
use QueryNova\Modules\Opportunities\Application\OpportunityDrawer;
use QueryNova\Modules\Opportunities\Application\RecommendationOutcome;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OutcomeModule extends AbstractModule {

    private RecommendationOutcome $outcomes;

    private ?RecommendationRepository $store = null;

    public function __construct( ?RecommendationRepository $store = null ) {
        $this->outcomes = new RecommendationOutcome( new ExperimentComparison() );
        $this->store    = $store;
    }

    public function getName(): string {
        return 'outcomes';
    }

    public function getDependencies(): array {
        return [ 'core', 'opportunities' ];
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.outcomes',
                    'Recommendation outcomes',
                    'outcomes',
                    QUERYNOVA_VERSION,
                    [ 'querynova.opportunities' ],
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
        $rest->route( 'POST', '/outcomes/accept', [ $this, 'acceptRoute' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/outcomes/apply', [ $this, 'applyRoute' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/outcomes/measure', [ $this, 'measureRoute' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/outcomes/drawer', [ $this, 'drawerRoute' ], Capability::RUN_ANALYSIS );
    }

    /**
     * @return array<string, mixed>
     */
    public function drawerRoute( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];
        $item   = isset( $params['item'] ) && is_array( $params['item'] ) ? $params['item'] : [];

        return [
            'detail' => OpportunityDrawer::present( $item ),
            'result' => OpportunityDrawer::act(
                (int) ( $params['id'] ?? 0 ),
                is_string( $params['action'] ?? null ) ? $params['action'] : '',
                ( $params['confirmed'] ?? false ) === true
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function acceptRoute( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return $this->accept( (int) ( $params['id'] ?? 0 ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function applyRoute( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return $this->markApplied( (int) ( $params['id'] ?? 0 ), $this->snapshot( $params['before'] ?? null ), $this->snapshot( $params['after'] ?? null ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function measureRoute( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return $this->measure( (int) ( $params['id'] ?? 0 ), $this->metrics( $params['before'] ?? [] ), $this->metrics( $params['after'] ?? [] ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function accept( int $id ): array {
        $changed = $this->repository()->transition( $id, 'suggested', 'accepted' );

        return [
            'id'       => $id,
            'accepted' => $changed,
            'applied'  => false,
            'note'     => 'Accepting a recommendation does not change the page.',
        ];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @return array<string, mixed>
     */
    public function markApplied( int $id, ?array $before, ?array $after ): array {
        $changed = $this->repository()->transition( $id, 'accepted', 'applied' );
        if ( $changed ) {
            $this->repository()->recordAction( $id, 0, 'marked_applied', $before, $after );
        }

        return [
            'id'           => $id,
            'applied'      => $changed,
            'changed_page' => false,
            'note'         => 'The recommendation was marked applied. QueryNova did not change the page.',
        ];
    }

    /**
     * @param array<string, float|int|null> $before
     * @param array<string, float|int|null> $after
     * @return array<string, mixed>
     */
    public function measure( int $id, array $before, array $after ): array {
        $row = $this->repository()->find( $id );
        if ( $row === null || (string) $row['status'] !== 'applied' ) {
            return [
                'id'        => $id,
                'measured'  => false,
                'outcome'   => null,
                'causation' => false,
                'note'      => 'Performance can be measured only after the recommendation is marked applied.',
            ];
        }
        $result = $this->outcomes->measure( $before, $after );
        if ( $result['ready'] !== true ) {
            return [
                'id'        => $id,
                'measured'  => false,
                'outcome'   => null,
                'causation' => false,
                'note'      => (string) $result['note'],
            ];
        }
        $outcome = (string) $result['outcome'];
        $this->repository()->transition( $id, 'applied', 'measured', $outcome );

        return [
            'id'        => $id,
            'measured'  => true,
            'outcome'   => $outcome,
            'causation' => false,
            'note'      => (string) $result['note'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshot( mixed $value ): ?array {
        if ( ! is_array( $value ) ) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<string, float|int|null>
     */
    private function metrics( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $metrics = [];
        foreach ( [ 'rank', 'ctr', 'clicks', 'traffic', 'revenue' ] as $metric ) {
            if ( ! array_key_exists( $metric, $value ) ) {
                continue;
            }
            $item = $value[ $metric ];
            if ( $item === null || is_int( $item ) || is_float( $item ) ) {
                $metrics[ $metric ] = $item;
            }
        }

        return $metrics;
    }

    private function repository(): RecommendationRepository {
        if ( ! $this->store instanceof RecommendationRepository ) {
            $this->store = new RecommendationRepository( new ArrayDatabase() );
        }

        return $this->store;
    }
}
