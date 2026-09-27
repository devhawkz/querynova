<?php
/**
 * WordPress options with autoload kept off for large values.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OptionStore {

    public function get( string $key, mixed $fallback = false ): mixed {
        return get_option( $key, $fallback );
    }

    public function set( string $key, mixed $value, bool $autoload = false ): void {
        if ( get_option( $key, '__querynova_missing__' ) === '__querynova_missing__' ) {
            add_option( $key, $value, '', $autoload );

            return;
        }
        update_option( $key, $value, $autoload );
    }

    public function delete( string $key ): void {
        delete_option( $key );
    }
}
