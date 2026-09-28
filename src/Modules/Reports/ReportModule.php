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
use QueryNova\Modules\Reports\Application\ReportSchedule;
use QueryNova\Modules\Reports\Application\RoleCatalog;
use QueryNova\Modules\Reports\Application\SettingsTransfer;
use QueryNova\Modules\Reports\Application\WhiteLabel;

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
        $rest->route( 'GET', '/reports/workspace', [ $this, 'workspace' ], Capability::VIEW_ANALYTICS );
        $rest->route( 'POST', '/reports/schedule', [ $this, 'schedule' ], Capability::VIEW_ANALYTICS );
        $rest->route( 'POST', '/reports/white-label', [ $this, 'whiteLabel' ], Capability::MANAGE_SETTINGS );
        $rest->route( 'POST', '/reports/roles', [ $this, 'roles' ], Capability::MANAGE_SETTINGS );
        $rest->route( 'POST', '/reports/settings/export', [ $this, 'exportSettings' ], Capability::MANAGE_SETTINGS );
        $rest->route( 'POST', '/reports/settings/import', [ $this, 'importSettings' ], Capability::MANAGE_SETTINGS );
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

    /**
     * @return array<string, mixed>
     */
    public function workspace( \WP_REST_Request $request ): array {
        unset( $request );

        return self::workspaceSnapshot();
    }

    /**
     * @return array<string, mixed>
     */
    public function schedule( \WP_REST_Request $request ): array {
        $params = $this->body( $request );
        $emails = [];
        if ( isset( $params['recipients'] ) && is_array( $params['recipients'] ) ) {
            foreach ( $params['recipients'] as $email ) {
                if ( is_string( $email ) ) {
                    $emails[] = $email;
                }
            }
        }

        return ReportSchedule::plan(
            $emails,
            is_string( $params['kind'] ?? null ) ? $params['kind'] : '',
            is_string( $params['frequency'] ?? null ) ? $params['frequency'] : '',
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function whiteLabel( \WP_REST_Request $request ): array {
        $params = $this->body( $request );

        return WhiteLabel::save(
            is_string( $params['logo'] ?? null ) ? $params['logo'] : '',
            is_string( $params['brand'] ?? null ) ? $params['brand'] : '',
            is_string( $params['footer'] ?? null ) ? $params['footer'] : '',
            is_string( $params['sender'] ?? null ) ? $params['sender'] : '',
            ( $params['enabled'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function roles( \WP_REST_Request $request ): array {
        $params = $this->body( $request );
        $areas  = [];
        if ( isset( $params['areas'] ) && is_array( $params['areas'] ) ) {
            foreach ( $params['areas'] as $area ) {
                if ( is_string( $area ) ) {
                    $areas[] = $area;
                }
            }
        }

        return RoleCatalog::saveCustom(
            is_string( $params['name'] ?? null ) ? $params['name'] : '',
            $areas,
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function exportSettings( \WP_REST_Request $request ): array {
        $params = $this->body( $request );

        return SettingsTransfer::export( is_string( $params['section'] ?? null ) ? $params['section'] : '' );
    }

    /**
     * @return array<string, mixed>
     */
    public function importSettings( \WP_REST_Request $request ): array {
        $params  = $this->body( $request );
        $payload = isset( $params['payload'] ) && is_array( $params['payload'] ) ? $params['payload'] : [];

        return SettingsTransfer::import(
            is_string( $params['section'] ?? null ) ? $params['section'] : '',
            $payload,
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function workspaceSnapshot(): array {
        return [
            'pdf'        => false,
            'schedule'   => ReportSchedule::read(),
            'whiteLabel' => WhiteLabel::present(),
            'roles'      => RoleCatalog::present(),
            'kinds'      => [ 'organic', 'content', 'keyword', 'rank', 'index', 'woocommerce', 'ai_visibility' ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function body( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return is_array( $params ) ? $params : [];
    }
}
