<?php
/**
 * Activates QueryNova on a WordPress release through the WordPress test library.
 *
 * This file is outside the default PHPUnit suite. `composer test` does not boot WordPress.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Environments;

use WP_Error;
use WP_UnitTestCase;

final class WordPressReleaseTest extends WP_UnitTestCase {

	public function testPluginActivatesOnTheRelease(): void {
		self::assertTrue( function_exists( 'tests_add_filter' ) );

		$expected = getenv( 'QUERYNOVA_WP_VERSION' );
		self::assertIsString( $expected );
		self::assertNotSame( '', $expected );
		self::assertSame( $expected, $GLOBALS['wp_version'] );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = activate_plugin( 'querynova/querynova.php' );
		if ( $result instanceof WP_Error ) {
			self::fail( implode( ' ', $result->get_error_messages() ) );
		}

		self::assertNull( $result );
		self::assertTrue( is_plugin_active( 'querynova/querynova.php' ) );
		self::assertSame( QUERYNOVA_DB_VERSION, get_option( 'querynova_db_version' ) );
	}
}
