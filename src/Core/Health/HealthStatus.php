<?php
/**
 * Health status values.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Health;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum HealthStatus: string {

    case Healthy   = 'HEALTHY';
    case Degraded  = 'DEGRADED';
    case Unhealthy = 'UNHEALTHY';
    case Disabled  = 'DISABLED';
}
