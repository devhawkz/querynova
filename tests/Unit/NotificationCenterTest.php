<?php
/**
 * The notification center stays inside QueryNova.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ReleaseProfile;
use QueryNova\Modules\Core\NotificationCenter;

final class NotificationCenterTest extends TestCase {

    public function testNoticesStayInsideQueryNovaAndDoNotHideSecurity(): void {
        $empty    = NotificationCenter::collect( [], null, 'normal', [] );
        $feed     = NotificationCenter::collect(
            [ 'Staging has a stored Search Console or GA4 property. If this site was cloned from production, that property still points at the production property. No analytics were read.' ],
            ReleaseProfile::STAGING_ON_PRODUCTION,
            'warning',
            [ 'Yoast', '' ]
        );
        $center   = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/NotificationCenter.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        $staging  = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/StagingDataNotice.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        $build    = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/BuildChannelNotice.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        $conflict = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Seo/Presentation/SeoConflictNotice.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.

        self::assertSame( [], $empty['items'] );
        self::assertFalse( $empty['hides_security'] );
        self::assertFalse( $empty['added_admin_notice'] );
        self::assertCount( 3, $feed['items'] );
        self::assertSame( 'warning', $feed['items'][1]['level'] );
        self::assertSame( ReleaseProfile::STAGING_ON_PRODUCTION, $feed['items'][1]['message'] );
        self::assertStringContainsString( 'did not disable the other plugin', $feed['items'][2]['message'] );
        self::assertFalse( $feed['hides_security'] );
        self::assertFalse( $feed['added_admin_notice'] );
        self::assertIsString( $center );
        self::assertIsString( $staging );
        self::assertIsString( $build );
        self::assertIsString( $conflict );
        self::assertStringNotContainsString( 'admin_notices', $center );
        self::assertStringNotContainsString( 'add_action', $center );
        self::assertStringContainsString( 'admin_notices', $staging );
        self::assertStringContainsString( 'admin_notices', $build );
        self::assertStringContainsString( 'admin_notices', $conflict );
    }
}
