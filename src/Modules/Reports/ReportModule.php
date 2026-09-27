<?php
/**
 * Report export. A request renders supplied metrics and does not fetch them.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Reports;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Reports\Application\ReportBuilder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ReportModule extends AbstractModule {

    private ReportBuilder $builder;

    public function __construct() {
        $this->builder = new ReportBuilder();
    }

    public function getName(): string {
        return 'reports';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.reports',
                    'Reports',
                    'reports',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::VIEW_ANALYTICS ],
                )
            );
        }
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/reports', [ $this, 'create' ], Capability::VIEW_ANALYTICS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function create( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        try {
            return $this->render(
                trim( (string) ( $params['kind'] ?? '' ) ),
                $this->metrics( $params['metrics'] ?? null ),
                trim( (string) ( $params['format'] ?? 'json' ) )
            );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_report', $exception->getMessage(), [ 'status' => 400 ] );
        }
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    public function render( string $kind, array $metrics, string $format ): array {
        return $this->builder->build( $kind, $metrics, $format );
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $metrics = [];
        foreach ( $value as $key => $item ) {
            if ( is_string( $key ) ) {
                $metrics[ $key ] = $item;
            }
        }

        return $metrics;
    }
}
