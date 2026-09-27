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

$querynova_tables = [
    'queries',
    'keywords',
    'keyword_clusters',
    'keyword_cluster_members',
    'pages',
    'products',
    'categories',
    'query_pages',
    'serp_snapshots',
    'serp_results',
    'rank_history',
    'competitors',
    'backlink_snapshots',
    'gsc_metrics',
    'ga_metrics',
    'commerce_metrics',
    'product_metrics',
    'category_metrics',
    'revenue_metrics',
    'content_analysis',
    'entities',
    'entity_relations',
    'internal_links',
    'ai_prompts',
    'ai_runs',
    'ai_mentions',
    'issues',
    'recommendations',
    'actions',
    'experiments',
    'jobs',
    'audit_log',
    'logs',
    'redirects',
    'not_found',
    'alerts',
    'migrations',
    'provider_usage',
];

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
