<?php
/**
 * Decay and cannibalization stay empty when a side is missing.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Content\Application\ContentHealth;
use QueryNova\Modules\Content\ContentModule;

final class ContentHealthTest extends TestCase {

    public function testDecayLeavesAMissingSideEmptyAndDoesNotClaimACause(): void {
        $report = ContentHealth::decay(
            [
                [
                    'url'      => 'https://example.test/lager',
                    'current'  => [ 'clicks' => 4 ],
                    'previous' => [],
                ],
            ]
        );

        self::assertFalse( $report['crawled'] );
        self::assertFalse( $report['causation'] );
        self::assertNull( $report['rows'][0]['previous']['clicks'] );
        self::assertNull( $report['rows'][0]['current']['impressions'] );
        self::assertSame( 4, $report['rows'][0]['current']['clicks'] );
        self::assertNull( $report['rows'][0]['change']['clicks'] );
        self::assertStringContainsString( 'does not claim a cause', $report['note'] );
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Content/Application/ContentHealth.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        self::assertIsString( $source );
        self::assertStringNotContainsString( 'wp_remote_', $source );
    }

    public function testCannibalizationUsesStoredRowsAndSkipsAMissingUrl(): void {
        $report = ContentHealth::cannibalization(
            [
                [
                    'keyword' => 'Lager',
                    'url'     => 'https://example.test/a',
                ],
                [
                    'keyword' => 'lager',
                    'url'     => 'https://example.test/b',
                ],
                [
                    'keyword' => 'lager',
                    'url'     => '',
                ],
            ]
        );
        $routes = new RestRegistrar();
        ( new ContentModule() )->registerRoutes( $routes );

        self::assertFalse( $report['crawled'] );
        self::assertFalse( $report['causation'] );
        self::assertCount( 1, $report['rows'] );
        self::assertSame( [ 'https://example.test/a', 'https://example.test/b' ], $report['rows'][0]['urls'] );
        self::assertFalse( $report['rows'][0]['causation'] );
        self::assertContains( '/content/health', array_column( $routes->routes(), 'route' ) );
    }
}
