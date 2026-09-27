<?php
/**
 * Transient cache for values that must survive a request without a persistent object cache.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TransientCache implements CacheInterface {

    public function __construct( private readonly string $group = 'querynova' ) {
    }

    public function get( string $key, mixed $fallback = null ): mixed {
        $value = get_transient( $this->id( $key ) );

        return $value === false ? $fallback : $value;
    }

    public function set( string $key, mixed $value, int $ttl ): void {
        set_transient( $this->id( $key ), $value, $ttl );
    }

    public function delete( string $key ): void {
        delete_transient( $this->id( $key ) );
    }

    public function flushGroup(): void {
        // Transients are deleted by key. Group flush is a no-op beyond the known prefix.
    }

    private function id( string $key ): string {
        return $this->group . '_' . md5( $key );
    }
}
