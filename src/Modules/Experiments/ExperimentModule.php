<?php
/**
 * Experiments record before and after. They do not claim the change caused the movement.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experiments;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\ValidationException;
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
use QueryNova\Modules\Experiments\Infrastructure\ExperimentRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperimentModule extends AbstractModule {

    private ExperimentComparison $comparison;

    private ?ExperimentRepository $store = null;

    public function __construct() {
        $this->comparison = new ExperimentComparison();
    }

    public function getName(): string {
        return 'experiments';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.experiments',
                    'Experiments',
                    'experiments',
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
        $this->store = new ExperimentRepository( $database );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/experiments', [ $this, 'create' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/experiments/close', [ $this, 'finish' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/experiments', [ $this, 'show' ], Capability::VIEW_ANALYTICS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function create( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        try {
            return $this->open( $params );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_experiment', $exception->getMessage(), [ 'status' => 400 ] );
        }
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function finish( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        try {
            return $this->close( (int) ( $params['id'] ?? 0 ), $this->metrics( $params['after'] ?? [] ) );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_experiment', $exception->getMessage(), [ 'status' => 400 ] );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $row = $this->repository()->find( (int) $request->get_param( 'id' ) );
        if ( $row === null ) {
            return [
                'status'    => 'unavailable',
                'causation' => false,
                'note'      => ExperimentComparison::NOTE,
            ];
        }

        return [
            'status'    => (string) $row['status'],
            'result'    => (string) $row['result'],
            'causation' => false,
            'note'      => (string) $row['causation_note'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function open( array $input ): array {
        $name       = trim( (string) ( $input['name'] ?? '' ) );
        $type       = trim( (string) ( $input['type'] ?? '' ) );
        $objectType = trim( (string) ( $input['object_type'] ?? '' ) );
        if ( $name === '' || $objectType === '' ) {
            throw new ValidationException( 'Name and object type are required.' );
        }
        $before  = $this->metrics( $input['before'] ?? [] );
        $preview = $this->comparison->compare( $type, $before, [] );
        $id      = $this->repository()->open( $name, $type, $objectType, (int) ( $input['object_id'] ?? 0 ), $before, ExperimentComparison::NOTE );

        return [
            'id'        => $id,
            'status'    => 'running',
            'result'    => 'inconclusive',
            'causation' => false,
            'applied'   => false,
            'preview'   => $preview,
            'note'      => ExperimentComparison::NOTE,
        ];
    }

    /**
     * @param array<string, float|int|null> $after
     * @return array<string, mixed>
     */
    public function close( int $id, array $after ): array {
        $row = $this->repository()->find( $id );
        if ( $row === null ) {
            return [
                'status'    => 'unavailable',
                'causation' => false,
                'note'      => ExperimentComparison::NOTE,
            ];
        }
        $decoded          = json_decode( (string) $row['before_json'], true );
        $before           = $this->metrics( is_array( $decoded ) ? $decoded : [] );
        $report           = $this->comparison->compare( (string) $row['experiment_type'], $before, $after );
        $closed           = $this->repository()->close( $id, $after, (string) $report['result'], ExperimentComparison::NOTE );
        $report['closed'] = $closed;
        $report['id']     = $id;

        return $report;
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
            if ( $item === null ) {
                $metrics[ $metric ] = null;
                continue;
            }
            if ( is_int( $item ) || is_float( $item ) ) {
                $metrics[ $metric ] = $item;
            }
        }

        return $metrics;
    }

    private function repository(): ExperimentRepository {
        if ( ! $this->store instanceof ExperimentRepository ) {
            $this->store = new ExperimentRepository( new ArrayDatabase() );
        }

        return $this->store;
    }
}
