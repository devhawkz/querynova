<?php
/**
 * Module contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Contracts;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Health\HealthReport;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Infrastructure\Database\MigrationRegistrar;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Infrastructure\WordPress\AdminPageRegistrar;
use QueryNova\Infrastructure\WordPress\CapabilityRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ModuleInterface {

    public function getName(): string;

    public function getVersion(): string;

    /**
     * @return list<string>
     */
    public function getDependencies(): array;

    public function isOptional(): bool;

    public function register( ContainerInterface $container ): void;

    public function boot( ContainerInterface $container ): void;

    public function registerHooks( HookRegistrar $hooks ): void;

    public function registerRoutes( RestRegistrar $rest ): void;

    public function registerJobs( JobRegistrar $jobs ): void;

    public function registerCapabilities( CapabilityRegistrar $capabilities ): void;

    public function registerMigrations( MigrationRegistrar $migrations ): void;

    public function registerAdminPages( AdminPageRegistrar $admin ): void;

    public function healthCheck(): HealthReport;
}
