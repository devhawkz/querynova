<?php
/**
 * Cache contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface CacheInterface {

    public function get( string $key, mixed $fallback = null ): mixed;

    public function set( string $key, mixed $value, int $ttl ): void;

    public function delete( string $key ): void;

    public function flushGroup(): void;
}
