<?php
/**
 * WordPress object cache adapter.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressObjectCache implements CacheInterface {

    public function __construct( private readonly string $group = 'querynova' ) {
    }

    public function get( string $key, mixed $fallback = null ): mixed {
        $found = false;
        $value = wp_cache_get( $key, $this->group, false, $found );

        return $found ? $value : $fallback;
    }

    public function set( string $key, mixed $value, int $ttl ): void {
        wp_cache_set( $key, $value, $this->group, $ttl );
    }

    public function delete( string $key ): void {
        wp_cache_delete( $key, $this->group );
    }

    public function flushGroup(): void {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( $this->group );

            return;
        }
        wp_cache_flush();
    }
}
