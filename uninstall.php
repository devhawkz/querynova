<?php
/**
 * Uninstall. Data is retained unless the site explicitly opted into cleanup.
 *
 * @package QueryNova
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( get_option( 'querynova_delete_data_on_uninstall' ) !== 'yes' ) {
    return;
}

global $wpdb;

$querynova_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_file( $querynova_autoload ) ) {
    require_once $querynova_autoload;
}

$querynova_tables = class_exists( \QueryNova\Infrastructure\Database\Schema::class )
    ? ( new \QueryNova\Infrastructure\Database\Schema() )->tableNames()
    : [];

foreach ( $querynova_tables as $querynova_table ) {
    // Table names come from the fixed list above, not from user input.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . 'qn_' . $querynova_table ) . '`' );
}

$querynova_option_names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'querynova\\_%'" );
if ( is_array( $querynova_option_names ) ) {
    foreach ( $querynova_option_names as $querynova_option_name ) {
        delete_option( (string) $querynova_option_name );
    }
}
