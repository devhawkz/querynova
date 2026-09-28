<?php
/**
 * A mismatched build shows a notice and does not stop the plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ReleaseProfile;
use QueryNova\Core\Security\Capability;
use QueryNova\Modules\Core\BuildChannelNotice;

final class BuildChannelNoticeTest extends TestCase {

    public function testWarningAndInfoMarkupStayNonBlocking(): void {
        $notice  = new BuildChannelNotice();
        $warning = $notice->markup( ReleaseProfile::assess( 'production', 'staging' ) );
        $info    = $notice->markup( ReleaseProfile::assess( 'staging', 'production' ) );

        self::assertStringContainsString( 'notice notice-warning', $warning );
        self::assertStringContainsString( ReleaseProfile::STAGING_ON_PRODUCTION, $warning );
        self::assertStringNotContainsString( 'wp_die', $warning );
        self::assertStringContainsString( 'notice notice-info', $info );
        self::assertStringContainsString( ReleaseProfile::PRODUCTION_ON_STAGING, $info );
        self::assertStringNotContainsString( ReleaseProfile::STAGING_ON_PRODUCTION, $info );
        self::assertSame( '', $notice->markup( ReleaseProfile::assess( 'production', 'production' ) ) );
        self::assertSame( '', $notice->markup( ReleaseProfile::assess( 'staging', 'staging' ) ) );
    }

    public function testRenderRequiresTheSettingsCapability(): void {
        $notice             = new BuildChannelNotice();
        $GLOBALS['qn_caps'] = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        ob_start();
        $notice->render();
        $hidden             = ob_get_clean();
        $GLOBALS['qn_caps'] = [ Capability::MANAGE_SETTINGS ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.

        self::assertSame( '', $hidden );
        unset( $GLOBALS['qn_caps'] );
    }
}
