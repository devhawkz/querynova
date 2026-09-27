<?php
/**
 * Health check contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Contracts;

use QueryNova\Core\Health\HealthReport;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface HealthCheckInterface {

    public function name(): string;

    public function check(): HealthReport;
}
