<?php
/**
 * Backlink counts, new and lost links, and the competitor gap.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Backlinks\Application\BacklinkGap;
use QueryNova\Modules\Backlinks\Application\BacklinkSummary;
use QueryNova\Modules\Backlinks\Domain\Backlink;
use QueryNova\Modules\Backlinks\Infrastructure\BacklinkRepository;
use QueryNova\Modules\Backlinks\Infrastructure\NullBacklinkProvider;

final class BacklinkTest extends TestCase {

    public function testMissingProviderIsNotZero(): void {
        $summary = ( new BacklinkSummary() )->summarize( null, null, '' );

        self::assertSame( 'unavailable', $summary['status'] );
        self::assertNull( $summary['backlinks'] );
        self::assertNull( $summary['referring_domains'] );
        self::assertNull( $summary['new'] );
        self::assertNull( $summary['lost'] );
    }

    public function testFirstSnapshotDoesNotCallEveryLinkNew(): void {
        $summary = ( new BacklinkSummary() )->summarize( $this->links(), null, 'provider_authority' );

        self::assertSame( 'measured', $summary['status'] );
        self::assertSame( 3, $summary['backlinks'] );
        self::assertSame( 2, $summary['referring_domains'] );
        self::assertSame( 2, $summary['dofollow'] );
        self::assertSame( 1, $summary['nofollow'] );
        self::assertNull( $summary['new'] );
        self::assertNull( $summary['lost'] );
        self::assertSame( 'provider_authority', $summary['authority_metric'] );
    }

    public function testSecondSnapshotCountsNewAndLost(): void {
        $previous = [
            hash( 'sha256', 'https://news.example/a' ),
            hash( 'sha256', 'https://blog.example/b' ),
        ];
        $current  = [
            new Backlink( 'https://news.example/a', 'https://shop.example/welder', 'welder', 'dofollow', null, null, 20.0, 'provider_authority' ),
            new Backlink( 'https://fresh.example/c', 'https://shop.example/welder', 'buy', 'dofollow', null, null, null, 'provider_authority' ),
        ];
        $summary  = ( new BacklinkSummary() )->summarize( $current, $previous, 'provider_authority' );

        self::assertSame( 1, $summary['new'] );
        self::assertSame( 1, $summary['lost'] );
    }

    public function testStoredAuthorityStaysNull(): void {
        $database = new ArrayDatabase();
        $store    = new BacklinkRepository( $database );
        $store->replace( 'https://shop.example/welder', 'fixture', $this->links() );
        $rows = $store->forTarget( 'https://shop.example/welder' );

        self::assertNull( $rows[0]['authority'] );
        self::assertSame( 'active', $rows[0]['link_status'] );
        $store->replace(
            'https://shop.example/welder',
            'fixture',
            [
                new Backlink( 'https://news.example/a', 'https://shop.example/welder', 'welder', 'dofollow', null, null, null, 'provider_authority' ),
            ]
        );
        $lost = $database->select( 'wp_qn_backlink_snapshots', [ 'source_hash' => hash( 'sha256', 'https://blog.example/b' ) ], 1 );

        self::assertSame( 'lost', $lost[0]['link_status'] );
    }

    public function testGapPrefersCompetitorOverlapAndSkipsOurDomains(): void {
        $rows = ( new BacklinkGap() )->missing(
            [ 'www.shop.example' ],
            [
                'rival-a.example' => [
                    [
                        'domain'    => 'news.example',
                        'authority' => null,
                    ],
                    [
                        'domain'    => 'shop.example',
                        'authority' => 90.0,
                    ],
                ],
                'rival-b.example' => [
                    [
                        'domain'    => 'news.example',
                        'authority' => 10.0,
                    ],
                    [
                        'domain'    => 'only-b.example',
                        'authority' => 40.0,
                    ],
                ],
            ]
        );

        self::assertSame( 'news.example', $rows[0]['domain'] );
        self::assertSame( 2, $rows[0]['overlap'] );
        self::assertSame( 10.0, $rows[0]['authority'] );
        self::assertSame( 'only-b.example', $rows[1]['domain'] );
        self::assertNotContains( 'shop.example', array_column( $rows, 'domain' ) );
    }

    public function testEmptyProviderListIsMeasuredZero(): void {
        $summary = ( new BacklinkSummary() )->summarize( [], null, '' );

        self::assertSame( 'measured', $summary['status'] );
        self::assertSame( 0, $summary['backlinks'] );
    }

    public function testNullProviderReturnsNoLinks(): void {
        self::assertNull( ( new NullBacklinkProvider() )->links( 'https://shop.example/' ) );
        self::assertSame( '', ( new NullBacklinkProvider() )->authorityMetric() );
    }

    public function testSafeModeOmitsBacklinks(): void {
        $names = [];
        foreach ( ModuleCatalog::modules( false ) as $module ) {
            $names[] = $module->getName();
        }

        self::assertContains( 'backlinks', $names );
        self::assertNotContains(
            'backlinks',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }

    /**
     * @return list<Backlink>
     */
    private function links(): array {
        return [
            new Backlink( 'https://news.example/a', 'https://shop.example/welder', 'welder', 'dofollow', null, null, null, 'provider_authority' ),
            new Backlink( 'https://www.news.example/a2', 'https://shop.example/welder', 'again', 'dofollow', null, null, 12.5, 'provider_authority' ),
            new Backlink( 'https://blog.example/b', 'https://shop.example/welder', 'review', 'nofollow', null, null, null, 'provider_authority' ),
        ];
    }
}
