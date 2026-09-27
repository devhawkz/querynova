<?php
/**
 * Activation requirements.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Requirements {

    /**
     * @return list<string>
     */
    public function failures(): array {
        global $wp_version, $wpdb;
        $prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) && is_string( $wpdb->prefix ) ? $wpdb->prefix : '';

        return $this->evaluate(
            PHP_VERSION,
            is_string( $wp_version ?? null ) ? $wp_version : null,
            $prefix !== '',
            extension_loaded( 'openssl' )
        );
    }

    public function requiresWooCommerce(): bool {
        return false;
    }

    /**
     * WooCommerce is optional. A store can connect it later. Its absence is not a failure.
     *
     * @return list<string>
     */
    public function evaluate( string $phpVersion, ?string $wpVersion, bool $databaseReady, bool $openSsl ): array {
        $failures = [];
        if ( version_compare( $phpVersion, '8.1', '<' ) ) {
            $failures[] = 'QueryNova requires PHP 8.1 or newer.';
        }
        if ( ! is_string( $wpVersion ) || version_compare( $wpVersion, '6.4', '<' ) ) {
            $failures[] = 'QueryNova requires WordPress 6.4 or newer.';
        }
        if ( ! $databaseReady ) {
            $failures[] = 'QueryNova requires a database connection.';
        }
        if ( ! $openSsl ) {
            $failures[] = 'QueryNova requires the OpenSSL extension.';
        }

        return $failures;
    }
}
