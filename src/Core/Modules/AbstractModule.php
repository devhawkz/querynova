<?php
/**
 * Default module behaviour. Feature modules override what they own.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Modules;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Contracts\ModuleInterface;
use QueryNova\Core\Health\HealthReport;
use QueryNova\Core\Health\HealthStatus;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Infrastructure\Database\MigrationRegistrar;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Infrastructure\WordPress\AdminPageRegistrar;
use QueryNova\Infrastructure\WordPress\CapabilityRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AbstractModule implements ModuleInterface {

    public function getVersion(): string {
        return QUERYNOVA_VERSION;
    }

    public function getDependencies(): array {
        return [ 'core' ];
    }

    public function isOptional(): bool {
        return true;
    }

    public function register( ContainerInterface $container ): void {
    }

    public function boot( ContainerInterface $container ): void {
    }

    public function registerHooks( HookRegistrar $hooks ): void {
    }

    public function registerRoutes( RestRegistrar $rest ): void {
    }

    public function registerJobs( JobRegistrar $jobs ): void {
    }

    public function registerCapabilities( CapabilityRegistrar $capabilities ): void {
    }

    public function registerMigrations( MigrationRegistrar $migrations ): void {
    }

    public function registerAdminPages( AdminPageRegistrar $admin ): void {
    }

    public function healthCheck(): HealthReport {
        return new HealthReport( $this->getName(), HealthStatus::Healthy, 'Module is registered.' );
    }
}
