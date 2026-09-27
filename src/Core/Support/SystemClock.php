<?php
/**
 * UTC system clock.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Support;

use QueryNova\Core\Contracts\ClockInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SystemClock implements ClockInterface {

    public function now(): \DateTimeImmutable {
        return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
    }
}
