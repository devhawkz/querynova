<?php
/**
 * Advanced details come from stored rows and do not fill missing numbers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Backlinks\Domain\Backlink;
use QueryNova\Modules\Backlinks\Infrastructure\BacklinkRepository;
use QueryNova\Modules\Core\AdvancedBriefing;
use QueryNova\Modules\Keywords\Domain\KeywordRecord;
use QueryNova\Modules\Keywords\Infrastructure\KeywordRepository;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;
use QueryNova\Modules\Serp\Domain\SerpHit;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Infrastructure\SerpRepository;

final class AdvancedBriefingTest extends TestCase {

    public function testEmptyStoresStayEmpty(): void {
        $sections = ( new AdvancedBriefing() )->fromDatabase( new ArrayDatabase() );

        foreach ( array_keys( AdvancedBriefing::emptySections() ) as $section ) {
            self::assertSame( [], $sections[ $section ], $section );
        }
    }

    public function testStoredRowsStayOutOfTheRawSectionAndDoNotInventZeros(): void {
        $database = new ArrayDatabase();
        ( new KeywordRepository( $database ) )->save(
            new KeywordRecord(
                0,
                'lager',
                'rs',
                'sr',
                null,
                null,
                0.4,
                40,
                [ 17.25 ],
                null,
                '',
                'fixture',
                'fixture-provider',
                'querynova.organic_difficulty'
            )
        );
        $query = new SerpQuery( 3, 'lager', 'Valjevo', 'rs', 'sr', 'desktop', 10, 'example.test' );
        $serp  = new SerpRepository( $database );
        $serp->saveSnapshot(
            $query,
            'fixture-provider',
            'ok',
            [ 'featured-snippet-token' ],
            null,
            [
                new SerpHit( 1, 'https://example.test/lager', 'example.test', 'Lager', 'secret-snippet', 'product', null ),
            ]
        );
        $serp->saveRank( $query, 'fixture-provider', null, 'https://example.test/lager' );
        ( new BacklinkRepository( $database ) )->replace(
            'https://example.test/lager',
            'fixture-provider',
            [
                new Backlink( 'https://source.example/a', 'https://example.test/lager', 'lager', 'nofollow', null, null, null, '' ),
            ]
        );
        ( new RecommendationRepository( $database ) )->save(
            [
                [
                    'title'      => 'Review the page',
                    'confidence' => 'LOW',
                    'impact'     => 'unavailable',
                ],
            ]
        );

        $sections = ( new AdvancedBriefing() )->fromDatabase( $database );
        $encoded  = (string) wp_json_encode( $sections );
        $keyword  = $sections['keywords'][0];

        self::assertSame( [], $sections['raw'] );
        self::assertSame( 'lager', $keyword['title'] );
        self::assertStringContainsString( 'Organic difficulty 40 · Estimated', (string) $keyword['summary'] );
        self::assertStringContainsString( 'Paid competition 0.4', (string) $keyword['summary'] );
        self::assertStringNotContainsString( 'Organic difficulty 0.4', (string) $keyword['summary'] );
        self::assertNull( $keyword['metric'] );
        self::assertSame( 'fixture-provider', $sections['serps'][0]['title'] );
        self::assertSame( 'source.example', $sections['backlinks'][0]['title'] );
        self::assertNull( $sections['backlinks'][0]['metric'] );
        self::assertSame( 'querynova.organic_difficulty', $sections['methodologies'][0]['title'] );
        self::assertSame( 'fixture-provider', $sections['providers'][0]['title'] );
        self::assertCount( 1, $sections['providers'] );
        self::assertSame( 'LOW', $sections['confidence'][0]['summary'] );
        self::assertNull( $sections['history'][0]['metric']['value'] );
        self::assertSame( 'UNAVAILABLE', $sections['history'][0]['metric']['kind'] );
        self::assertStringNotContainsString( 'secret-snippet', $encoded );
        self::assertStringNotContainsString( 'featured-snippet-token', $encoded );
        self::assertStringNotContainsString( '17.25', $encoded );
        self::assertStringNotContainsString( 'MEASURED', $encoded );
        self::assertStringNotContainsString( 'Authority 0', $encoded );
    }
}
