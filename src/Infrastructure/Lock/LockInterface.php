<?php
/**
 * Lock contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Lock;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface LockInterface {

    public function acquire( string $name, int $ttlSeconds ): bool;

    public function release( string $name ): void;
}
