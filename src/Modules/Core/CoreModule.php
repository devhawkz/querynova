<?php
/**
 * Core module. Required. Optional modules must not be able to take this down.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\ProductVersions;
use QueryNova\Core\Health\HealthReport;
use QueryNova\Core\Health\HealthStatus;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\WordPress\AdminPageRegistrar;
use QueryNova\Infrastructure\Database\MigrationRegistrar;
use QueryNova\Infrastructure\Database\Migrations\InitialSchemaMigration;
use QueryNova\Infrastructure\Database\Migrations\PageExperienceMigration;
use QueryNova\Infrastructure\Rest\RestRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CoreModule extends AbstractModule {

    public function getName(): string {
        return 'core';
    }

    public function getDependencies(): array {
        return [];
    }

    public function isOptional(): bool {
        return false;
    }

    public function registerMigrations( MigrationRegistrar $migrations ): void {
        $migrations->add( new InitialSchemaMigration() );
        $migrations->add( new PageExperienceMigration() );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'GET', '/status', [ $this, 'status' ], Capability::MANAGE_SETTINGS );
        $rest->route( 'GET', '/setup', [ $this, 'setup' ], Capability::MANAGE_SETTINGS );
        $rest->route( 'PUT', '/setup', [ $this, 'saveSetup' ], Capability::MANAGE_SETTINGS );
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        $hooks->add( new AdminAssets() );
        $hooks->add( new StagingDataNotice( new StagingDataWarning(), new SetupWizard( new \QueryNova\Infrastructure\WordPress\OptionStore() ) ) );
    }

    public function registerAdminPages( AdminPageRegistrar $admin ): void {
        $admin->add( 'querynova', __( 'What Matters Now', 'querynova' ), Capability::MANAGE_SETTINGS, [ $this, 'renderApp' ] );
    }

    public function renderApp(): void {
        if ( ! current_user_can( Capability::MANAGE_SETTINGS ) ) {
            wp_die( esc_html__( 'You do not have permission to view QueryNova.', 'querynova' ) );
        }

        echo '<div class="wrap">';
        echo '<h1 class="screen-reader-text">' . esc_html__( 'What Matters Now', 'querynova' ) . '</h1>';
        echo '<div id="querynova-admin"></div>';
        echo '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    public function status( mixed $request = null ): array {
        unset( $request );

        return [
            'name'           => 'QueryNova',
            'version'        => QUERYNOVA_VERSION,
            'schema_version' => (string) get_option( 'querynova_db_version', '0' ),
            'versions'       => ( new ProductVersions() )->describe(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setup( mixed $request = null ): array {
        unset( $request );

        return $this->wizard()->read( class_exists( 'WooCommerce' ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function saveSetup( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return $this->wizard()->save( $params, class_exists( 'WooCommerce' ) );
    }

    private function wizard(): SetupWizard {
        return new SetupWizard( new \QueryNova\Infrastructure\WordPress\OptionStore() );
    }

    public function healthCheck(): HealthReport {
        return new HealthReport( 'core', HealthStatus::Healthy, 'Core is loaded.' );
    }

    public function register( ContainerInterface $container ): void {
    }
}
