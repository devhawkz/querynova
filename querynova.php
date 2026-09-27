<?php
/**
 * Plugin Name: QueryNova
 * Plugin URI: https://github.com/devhawkz/querynova
 * Description: Search Intelligence to Revenue. SEO, commerce, and AI search intelligence for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: QueryNova
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: querynova
 * Domain Path: /languages
 *
 * @package QueryNova
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'QUERYNOVA_VERSION', '0.1.0' );
define( 'QUERYNOVA_FILE', __FILE__ );
define( 'QUERYNOVA_PATH', plugin_dir_path( __FILE__ ) );
define( 'QUERYNOVA_URL', plugin_dir_url( __FILE__ ) );
define( 'QUERYNOVA_BASENAME', plugin_basename( __FILE__ ) );
define( 'QUERYNOVA_DB_VERSION', '202609270001' );

$querynovaAutoload = __DIR__ . '/vendor/autoload.php';
if ( is_file( $querynovaAutoload ) ) {
    require_once $querynovaAutoload;
}

register_activation_hook( __FILE__, [ 'QueryNova\\Core\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'QueryNova\\Core\\Plugin', 'deactivate' ] );

add_action(
    'plugins_loaded',
    static function (): void {
		load_plugin_textdomain( 'querynova', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		QueryNova\Core\Plugin::boot();
	}
);

add_filter(
    'cron_schedules',
    static function ( array $schedules ): array {
		$schedules['querynova_quarter_hour'] = [
			'interval' => 15 * 60,
			'display'  => __( 'QueryNova every 15 minutes', 'querynova' ),
		];

		return $schedules;
	}
);
