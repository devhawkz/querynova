<?php
/**
 * Activates a WooCommerce release, then QueryNova, through the WordPress test library.
 *
 * This file is outside the default PHPUnit suite. `composer test` does not boot WooCommerce.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Environments;

use WP_Error;
use WP_UnitTestCase;

final class WooCommerceReleaseTest extends WP_UnitTestCase {

	public function testStoreAndPluginActivateOnTheRelease(): void {
		self::assertTrue( function_exists( 'tests_add_filter' ) );

		$wordpress = getenv( 'QUERYNOVA_WP_VERSION' );
		$commerce  = getenv( 'QUERYNOVA_WC_VERSION' );
		self::assertIsString( $wordpress );
		self::assertIsString( $commerce );
		self::assertNotSame( '', $commerce );
		self::assertSame( $wordpress, $GLOBALS['wp_version'] );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$store = activate_plugin( 'woocommerce/woocommerce.php' );
		if ( $store instanceof WP_Error ) {
			self::fail( implode( ' ', $store->get_error_messages() ) );
		}

		self::assertNull( $store );
		self::assertTrue( class_exists( 'WooCommerce' ) );
		self::assertSame( $commerce, WC_VERSION );
		self::assertTrue( function_exists( 'wc_get_orders' ) );

		$plugin = activate_plugin( 'querynova/querynova.php' );
		if ( $plugin instanceof WP_Error ) {
			self::fail( implode( ' ', $plugin->get_error_messages() ) );
		}

		self::assertNull( $plugin );
		self::assertTrue( is_plugin_active( 'querynova/querynova.php' ) );
		self::assertSame( QUERYNOVA_DB_VERSION, get_option( 'querynova_db_version' ) );
		self::assertTrue( class_exists( 'WooCommerce' ) );
	}
}
