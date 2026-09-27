<?php
/**
 * Keyword discovery, difficulty, gap, and storage.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Domain\ProvenanceKind;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Keywords\Application\KeywordIntelligence;
use QueryNova\Modules\Keywords\Domain\KeywordRecord;
use QueryNova\Modules\Keywords\Infrastructure\KeywordRepository;
use QueryNova\Modules\Keywords\Infrastructure\NullAdsProvider;
use QueryNova\Tests\Support\FixedAdsProvider;

final class KeywordTest extends TestCase {

    public function testExplorerLeavesMissingMetricsUnavailable(): void {
        $view = ( new KeywordIntelligence( new NullAdsProvider() ) )->explore( $this->record( 'alpha welder' ) );

        self::assertNull( $view['volume']['value'] );
        self::assertSame( ProvenanceKind::Unavailable->value, $view['volume']['provenance'] );
        self::assertSame( ProvenanceKind::Unavailable->value, $view['organic_difficulty']['provenance'] );
        self::assertSame( ProvenanceKind::Unavailable->value, $view['paid_competition']['provenance'] );
        self::assertSame( ProvenanceKind::Unavailable->value, $view['opportunity']['status'] );
        self::assertSame( ProvenanceKind::Unavailable->value, $view['traffic_potential']['provenance'] );
    }

    public function testAdsMetricsDoNotBecomeOrganicDifficulty(): void {
        $ads  = new FixedAdsProvider();
        $next = ( new KeywordIntelligence( $ads ) )->withAds( $this->record( 'alpha welder' ) );

        self::assertSame( 90, $next->volume );
        self::assertSame( 0.8, $next->paidCompetition );
        self::assertNull( $next->organicDifficulty );
        self::assertSame( 'fixture', $next->provider );
    }

    public function testDifficultyIsUnavailableUntilEveryInputExists(): void {
        $engine  = new KeywordIntelligence( new NullAdsProvider() );
        $partial = $engine->difficulty( [ 'top10_authority' => 80 ] );
        $inputs  = [];
        foreach ( [ 'top10_authority', 'page_strength', 'referring_domains', 'serp_stability', 'brand_dominance', 'page_type_consistency', 'content_strength', 'topical_authority', 'serp_features' ] as $key ) {
            $inputs[ $key ] = 50.0;
        }
        $full = $engine->difficulty( $inputs );

        self::assertNull( $partial['value'] );
        self::assertSame( ProvenanceKind::Unavailable->value, $partial['provenance'] );
        self::assertSame( 50, $full['value'] );
        self::assertSame( ProvenanceKind::Estimated->value, $full['provenance'] );
        self::assertSame( KeywordIntelligence::DIFFICULTY_METHOD, $full['methodology'] );
    }

    public function testDiscoveryGroupsSeedsAndSuggestsProductQueries(): void {
        $groups = ( new KeywordIntelligence( new NullAdsProvider() ) )->discover(
            [
                [
                    'text' => 'Alpha 200',
                    'kind' => 'products',
                ],
                [
                    'text' => 'best alpha',
                    'kind' => '',
                ],
            ]
        );

        self::assertSame( 'Alpha 200', $groups['products'][0]['text'] );
        self::assertFalse( $groups['products'][0]['suggested'] );
        self::assertTrue( $groups['questions'][0]['suggested'] );
        self::assertSame( 'best alpha', $groups['commercial'][0]['text'] );
        self::assertNull( $groups['products'][0]['volume'] ?? null );
    }

    public function testGapRanksOnlyMeasuredPositions(): void {
        $engine = new KeywordIntelligence( new NullAdsProvider() );
        $gap    = $engine->gap(
            [
                [
                    'keyword' => 'alpha',
                    'rank'    => 3,
                ],
                [
                    'keyword' => 'beta',
                    'rank'    => 12,
                ],
                [
                    'keyword' => 'gamma',
                    'rank'    => null,
                ],
            ],
            [
                [
                    'keyword' => 'alpha',
                    'rank'    => 8,
                ],
                [
                    'keyword' => 'beta',
                    'rank'    => 4,
                ],
                [
                    'keyword' => 'gamma',
                    'rank'    => 2,
                ],
                [
                    'keyword' => 'delta welder',
                    'rank'    => 5,
                ],
            ],
            [ 'welder' ],
            [ 'epsilon' ]
        );

        self::assertSame( [ 'alpha' ], $gap['strong'] );
        self::assertSame( [ 'beta' ], $gap['weak'] );
        self::assertContains( 'gamma', $gap['shared'] );
        self::assertContains( 'delta welder', $gap['missing'] );
        self::assertContains( 'delta welder', $gap['commerce'] );
        self::assertSame( [ 'epsilon' ], $gap['untapped'] );
    }

    public function testWinnableTrafficMappingAndCannibalization(): void {
        $engine    = new KeywordIntelligence( new NullAdsProvider() );
        $blocked   = $engine->winnable(
            [
				'demand'  => 100,
				'revenue' => null,
			]
        );
        $winnable  = $engine->winnable(
            [
                'demand'      => 100,
                'competition' => 20,
                'authority'   => 10,
                'position'    => 18,
                'inventory'   => 4,
                'revenue'     => 0,
                'margin'      => 1,
                'content'     => 1,
            ]
        );
        $traffic   = $engine->traffic( [ 100, null ] );
        $measured  = $engine->traffic( [ 100, 300 ] );
        $mapped    = $engine->mapContent(
            'alpha',
            [
                [
                    'type' => 'article',
                    'name' => 'Alpha guide',
                    'url'  => '/guide',
                ],
                [
                    'type' => 'product',
                    'name' => 'Alpha 200',
                    'url'  => '/alpha',
                ],
            ]
        );
        $conflicts = $engine->cannibalization(
            [
                [
                    'keyword' => 'Alpha',
                    'url'     => '/alpha',
                    'type'    => 'product',
                ],
                [
                    'keyword' => 'alpha',
                    'url'     => '/category/alpha',
                    'type'    => 'category',
                ],
            ]
        );
        $clusters  = $engine->cluster(
            [
                [
                    'text'   => 'alpha welder',
                    'intent' => 'transactional',
                ],
                [
                    'text'   => 'alpha price',
                    'intent' => 'transactional',
                ],
            ]
        );

        self::assertNull( $blocked['winnable'] );
        self::assertContains( 'revenue', $blocked['missing'] );
        self::assertTrue( $winnable['winnable'] );
        self::assertSame( ProvenanceKind::Estimated->value, $winnable['status'] );
        self::assertNull( $traffic['estimated_traffic'] );
        self::assertSame( 100, $measured['estimated_traffic'] );
        self::assertSame( ProvenanceKind::Estimated->value, $measured['provenance'] );
        self::assertSame( 'product', $mapped['type'] );
        self::assertSame( '/alpha', $mapped['url'] );
        self::assertCount( 1, $conflicts );
        self::assertSame( [ 'product', 'category' ], $conflicts[0]['types'] );
        self::assertSame( 'product', $clusters[0]['page'] );
    }

    public function testStoredKeywordKeepsVolumeNull(): void {
        $store = new KeywordRepository( new ArrayDatabase() );
        $store->save( $this->record( 'alpha welder' ) );
        $loaded = $store->all()[0];

        self::assertSame( 'alpha welder', $loaded->keyword );
        self::assertNull( $loaded->volume );
        self::assertNull( $loaded->organicDifficulty );
        self::assertNull( $loaded->paidCompetition );
    }

    private function record( string $keyword ): KeywordRecord {
        return new KeywordRecord( 0, $keyword, 'us', 'en', null, null, null, null, null, null, '', 'manual', '', null );
    }
}
