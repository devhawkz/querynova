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
        $failures = [];
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            $failures[] = 'QueryNova requires PHP 8.1 or newer.';
        }
        global $wp_version;
        if ( is_string( $wp_version ?? null ) && version_compare( $wp_version, '6.4', '<' ) ) {
            $failures[] = 'QueryNova requires WordPress 6.4 or newer.';
        }

        return $failures;
    }
}
