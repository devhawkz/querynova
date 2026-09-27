<?php
/**
 * Adapts a module healthCheck() into the health registry.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Health;

use QueryNova\Core\Contracts\HealthCheckInterface;
use QueryNova\Core\Contracts\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ModuleHealthCheck implements HealthCheckInterface {

    public function __construct( private readonly ModuleInterface $module ) {
    }

    public function name(): string {
        return 'module:' . $this->module->getName();
    }

    public function check(): HealthReport {
        return $this->module->healthCheck();
    }
}
