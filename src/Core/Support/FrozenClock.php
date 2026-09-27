<?php
/**
 * Frozen clock for tests and deterministic jobs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Support;

use QueryNova\Core\Contracts\ClockInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FrozenClock implements ClockInterface {

    public function __construct( private \DateTimeImmutable $now ) {
    }

    public function now(): \DateTimeImmutable {
        return $this->now;
    }

    public function advance( string $modifier ): void {
        $this->now = $this->now->modify( $modifier );
    }
}
