<?php
/**
 * Conflict detection warns and does not disable the other SEO plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Security\Capability;
use QueryNova\Modules\Seo\Application\SeoConflictDetector;
use QueryNova\Modules\Seo\Presentation\SeoConflictNotice;

final class SeoConflictTest extends TestCase {

    public function testNoOtherPluginProducesNoWarning(): void {
        $report = ( new SeoConflictDetector() )->detect(
            [
                'yoast'    => false,
                'rankmath' => false,
                'aioseo'   => false,
            ]
        );

        self::assertSame( [], $report['plugins'] );
        self::assertSame( [], $report['warnings'] );
        self::assertFalse( $report['disabled_other_plugin'] );
    }

    public function testDetectedPluginWarnsAboutDuplicateOutput(): void {
        $report = ( new SeoConflictDetector() )->detect(
            [
                'yoast'    => true,
                'rankmath' => false,
                'aioseo'   => true,
            ]
        );

        self::assertSame( [ 'Yoast', 'AIOSEO' ], $report['plugins'] );
        self::assertSame( SeoConflictDetector::AREAS, $report['warnings'][0]['areas'] );
        self::assertSame( [ 'meta', 'schema', 'canonical', 'sitemap' ], $report['warnings'][1]['areas'] );
        self::assertFalse( $report['disabled_other_plugin'] );
    }

    public function testNoticeStaysEmptyWithoutTheCapability(): void {
        $GLOBALS['qn_caps'] = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        ob_start();
        ( new SeoConflictNotice( new SeoConflictDetector() ) )->renderFrom( [ 'yoast' => true ] );
        $html = ob_get_clean();

        self::assertSame( '', $html );
    }

    public function testNoticeNamesThePluginAndDoesNotDisableIt(): void {
        $GLOBALS['qn_caps'] = [ Capability::MANAGE_SEO ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        ob_start();
        ( new SeoConflictNotice( new SeoConflictDetector() ) )->renderFrom(
            [
                'yoast'    => true,
                'rankmath' => false,
                'aioseo'   => false,
            ]
        );
        $html = ob_get_clean();

        self::assertIsString( $html );
        self::assertStringContainsString( 'Yoast', $html );
        self::assertStringContainsString( 'Meta, schema, canonical, and sitemap', $html );
        self::assertStringContainsString( 'did not disable', $html );
        self::assertStringNotContainsString( 'deactivate', strtolower( $html ) );
    }

    public function testRuntimeDetectionDoesNotInventAPlugin(): void {
        $active = ( new SeoConflictDetector() )->activePlugins();

        self::assertFalse( $active['yoast'] );
        self::assertFalse( $active['rankmath'] );
        self::assertFalse( $active['aioseo'] );
    }
}
