<?php
/**
 * Activation checks, deactivation cleanup, and uninstall retention.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Lifecycle;
use QueryNova\Core\Plugin;
use QueryNova\Core\Requirements;
use QueryNova\Infrastructure\Database\Schema;

final class LifecycleTest extends TestCase {

    public function testRequirementsRejectMissingRuntimePieces(): void {
        $requirements = new Requirements();
        $missing      = $requirements->evaluate( '8.0.0', null, false, false );

        self::assertContains( 'QueryNova requires PHP 8.1 or newer.', $missing );
        self::assertContains( 'QueryNova requires WordPress 6.4 or newer.', $missing );
        self::assertContains( 'QueryNova requires a database connection.', $missing );
        self::assertContains( 'QueryNova requires the OpenSSL extension.', $missing );
        self::assertFalse( $requirements->requiresWooCommerce() );
        self::assertSame( [], $requirements->evaluate( '8.3.0', '6.6', true, true ) );
    }

    public function testDefaultsKeepAnExplicitCleanupChoice(): void {
        unset( $GLOBALS['querynova_options'][ Lifecycle::DELETE_DATA_OPTION ] );
        $lifecycle = new Lifecycle();
        $lifecycle->ensureDefaults();
        self::assertSame( 'no', get_option( Lifecycle::DELETE_DATA_OPTION ) );

        update_option( Lifecycle::DELETE_DATA_OPTION, 'yes' );
        $lifecycle->ensureDefaults();
        self::assertSame( 'yes', get_option( Lifecycle::DELETE_DATA_OPTION ) );
    }

    public function testDeactivateClearsRuntimeAndKeepsStoredData(): void {
        $GLOBALS['querynova_options']['querynova_setup']      = [ 'site_type' => 'store' ];
        $GLOBALS['querynova_options']['querynova_db_version'] = '202609270002';
        $GLOBALS['qn_cron']['querynova_process_jobs']         = time(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores cron under this key.
        $GLOBALS['qn_transients']['querynova_ephemeral']      = [ // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores transients under this key.
            'value'   => 'cached',
            'expires' => time() + 60,
        ];
        $lockKey                              = 'querynova_lock_' . md5( 'crawl' );
        $GLOBALS['qn_transients'][ $lockKey ] = [ // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores transients under this key.
            'value'   => '1',
            'expires' => time() + 60,
        ];
        $GLOBALS['wpdb']                      = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double for the deactivation query.
            public string $options = 'wp_options';

            public string $prefix = 'wp_';

            public function prepare( string $sql, string $cache, string $timeout ): string {
                unset( $cache, $timeout );

                return $sql;
            }

            /**
             * @return list<string>
             */
            public function get_col( string $sql ): array {
                unset( $sql );

                return [ '_transient_querynova_ephemeral', '_transient_timeout_querynova_ephemeral' ];
            }
        };

        try {
            Plugin::deactivate();
        } finally {
            unset( $GLOBALS['wpdb'] );
        }

        self::assertSame( 'store', $GLOBALS['querynova_options']['querynova_setup']['site_type'] );
        self::assertSame( '202609270002', $GLOBALS['querynova_options']['querynova_db_version'] );
        self::assertArrayNotHasKey( 'querynova_process_jobs', $GLOBALS['qn_cron'] );
        self::assertFalse( get_transient( 'querynova_ephemeral' ) );
        self::assertFalse( get_transient( $lockKey ) );
    }

    public function testFullCleanupDropsEverySchemaTable(): void {
        $names = ( new Schema() )->tableNames();

        self::assertContains( 'page_experience', $names );
        self::assertContains( 'redirects', $names );
        self::assertContains( 'logs', $names );
        $uninstall = (string) file_get_contents( QUERYNOVA_PATH . 'uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        self::assertStringContainsString( 'tableNames()', $uninstall );
        self::assertStringContainsString( "get_option( 'querynova_delete_data_on_uninstall' ) !== 'yes'", $uninstall );
    }
}
