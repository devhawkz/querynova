<?php
/**
 * Background job states.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum JobStatus: string {

    case Pending   = 'PENDING';
    case Running   = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed    = 'FAILED';
    case Retrying  = 'RETRYING';
    case Cancelled = 'CANCELLED';
    case Dead      = 'DEAD';
}
