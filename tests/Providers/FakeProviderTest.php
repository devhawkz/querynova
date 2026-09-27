<?php
/**
 * Fake providers return fixtures and do not call a third party.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Providers;

use PHPUnit\Framework\TestCase;
use QueryNova\Modules\Ai\Domain\AiObservation;
use QueryNova\Modules\Analytics\Domain\AnalyticsRow;
use QueryNova\Modules\Backlinks\Domain\Backlink;
use QueryNova\Modules\Serp\Domain\SerpHit;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Domain\SerpSnapshot;

final class FakeProviderTest extends TestCase {

    public function testMissingFixturesStayUnavailable(): void {
        $query = new SerpQuery( 1, 'lager', '', 'rs', 'sr', 'desktop', 10, 'example.test' );

        self::assertNull( ( new FakeKeywordProvider( null ) )->metrics( 'lager', 'rs', 'sr' ) );
        self::assertNull( ( new FakeSerpProvider( null ) )->snapshot( $query ) );
        self::assertNull( ( new FakeBacklinkProvider( null ) )->links( 'https://example.test/' ) );
        self::assertNull( ( new FakeAnalyticsProvider( null ) )->rows( 'G-TEST', '2026-01-01', '2026-01-02' ) );
        self::assertNull( ( new FakeLlmProvider( null ) )->observe( 'who sells lager', 'sr' ) );
    }

    public function testFixturesRoundTripWithoutFillingMissingNumbers(): void {
        $keyword     = ( new FakeKeywordProvider(
            [
                'volume'           => null,
                'cpc'              => null,
                'paid_competition' => 0.2,
                'trend'            => null,
            ]
        ) )->metrics( 'lager', 'rs', 'sr' );
        $snapshot    = ( new FakeSerpProvider(
            new SerpSnapshot(
                [ new SerpHit( 1, 'https://example.test/lager', 'example.test', 'Lager', '', 'product', null ) ],
                null,
                '2026-09-27T00:00:00Z'
            )
        ) )->snapshot( new SerpQuery( 1, 'lager', '', 'rs', 'sr', 'desktop', 10, 'example.test' ) );
        $links       = ( new FakeBacklinkProvider(
            [
                new Backlink( 'https://other.test/a', 'https://example.test/', 'lager', 'nofollow', null, null, null, '' ),
            ]
        ) )->links( 'https://example.test/' );
        $rows        = ( new FakeAnalyticsProvider(
            [
                new AnalyticsRow( '2026-01-01', 4, 3, null, null, null, null, null, null, null, 'organic', 'search' ),
            ]
        ) )->rows( 'G-TEST', '2026-01-01', '2026-01-02' );
        $observation = ( new FakeLlmProvider(
            new AiObservation( true, false, false, null, [], [], 'Mentioned the brewery.' )
        ) )->observe( 'who sells lager', 'sr' );

        self::assertNotNull( $keyword );
        self::assertNull( $keyword['volume'] );
        self::assertSame( 0.2, $keyword['paid_competition'] );
        self::assertNotNull( $snapshot );
        self::assertNull( $snapshot->features );
        self::assertSame( 'https://example.test/lager', $snapshot->hits[0]->url );
        self::assertNotNull( $links );
        self::assertNull( $links[0]->authority );
        self::assertNotNull( $rows );
        self::assertNull( $rows[0]->revenue );
        self::assertNotNull( $observation );
        self::assertNull( $observation->citedUrl );
        self::assertSame( 'fake-model', ( new FakeLlmProvider( null ) )->model() );
    }

    public function testFakeSourcesDoNotCallRemoteApis(): void {
        foreach ( [ 'FakeKeywordProvider.php', 'FakeSerpProvider.php', 'FakeBacklinkProvider.php', 'FakeAnalyticsProvider.php', 'FakeLlmProvider.php' ] as $file ) {
            $source = (string) file_get_contents( QUERYNOVA_PATH . 'tests/Providers/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test double, not a remote request.
            self::assertStringNotContainsString( 'wp_remote_', $source );
            self::assertStringNotContainsString( 'curl_', $source );
        }
    }
}
