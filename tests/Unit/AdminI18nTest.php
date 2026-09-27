<?php
/**
 * Admin JavaScript is registered for the querynova text domain.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminI18nTest extends TestCase {

    public function testAdminScriptUsesWordPressTranslations(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/AdminAssets.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, not a remote request.
        $header = (string) file_get_contents( QUERYNOVA_PATH . 'querynova.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin header, not a remote request.

        self::assertStringContainsString( "'wp-i18n'", $source );
        self::assertStringContainsString( "wp_set_script_translations( 'querynova-admin', 'querynova'", $source );
        self::assertStringContainsString( 'Text Domain: querynova', $header );
    }
}
