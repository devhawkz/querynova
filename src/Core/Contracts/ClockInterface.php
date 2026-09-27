<?php
/**
 * Clock contract. Storage uses UTC.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ClockInterface {

    public function now(): \DateTimeImmutable;
}
