<?php
/**
 * Activation defaults and deactivation cleanup.
 *
 * Deactivation keeps stored data. It clears scheduled work, locks, and ephemeral cache.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

use QueryNova\Infrastructure\Lock\TransientLock;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lifecycle {

    public const DELETE_DATA_OPTION = 'querynova_delete_data_on_uninstall';

    /**
     * @var list<string>
     */
    private const LOCKS = [
        'crawl',
        'migration',
        'analytics-sync',
        'serp-batch',
        'backlink-batch',
        'ai-batch',
        'experience-batch',
    ];

    public function ensureDefaults(): void {
        if ( get_option( self::DELETE_DATA_OPTION, false ) === false ) {
            add_option( self::DELETE_DATA_OPTION, 'no', '', false );
        }
    }

    public function releaseRuntime(): void {
        wp_clear_scheduled_hook( 'querynova_process_jobs' );
        $locks = new TransientLock();
        foreach ( self::LOCKS as $name ) {
            $locks->release( $name );
        }
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'querynova' );
        }
        $this->deleteEphemeralTransients();
    }

    private function deleteEphemeralTransients(): void {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_col' ) || ! isset( $wpdb->options ) || ! is_string( $wpdb->options ) ) {
            return;
        }
        if ( ! method_exists( $wpdb, 'prepare' ) ) {
            return;
        }
        $cache   = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_querynova_' ) . '%' : '\_transient\_querynova\_%';
        $timeout = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_timeout_querynova_' ) . '%' : '\_transient\_timeout\_querynova\_%';
        $names   = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $cache, $timeout ) );
        if ( ! is_array( $names ) ) {
            return;
        }
        foreach ( $names as $name ) {
            if ( ! is_string( $name ) ) {
                continue;
            }
            $key = $name;
            if ( str_starts_with( $key, '_transient_timeout_' ) ) {
                $key = substr( $key, strlen( '_transient_timeout_' ) );
            } elseif ( str_starts_with( $key, '_transient_' ) ) {
                $key = substr( $key, strlen( '_transient_' ) );
            } else {
                continue;
            }
            if ( str_starts_with( $key, 'querynova_' ) ) {
                delete_transient( $key );
            }
        }
    }
}
