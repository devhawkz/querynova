<?php
/**
 * Content observations stay null when the document or dictionary is missing.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Content\Application\ContentIntelligence;
use QueryNova\Modules\Content\ContentModule;
use QueryNova\Modules\Content\Infrastructure\ContentRepository;

final class ContentTest extends TestCase {

    public function testUnknownQueryStaysUnknown(): void {
        $intent = ( new ContentIntelligence() )->intent( 'welders' );

        self::assertSame( 'unknown', $intent['primary'] );
        self::assertSame( 'UNKNOWN', $intent['confidence'] );
        self::assertStringContainsString( 'Not a Google classification', $intent['note'] );
    }

    public function testCommercialQueryCanCarryTransactionalSecondary(): void {
        $intent = ( new ContentIntelligence() )->intent( 'best welder price', [ 'comparison' ] );

        self::assertSame( 'commercial_investigation', $intent['primary'] );
        self::assertSame( 'transactional', $intent['secondary'] );
        self::assertSame( 'MEDIUM', $intent['confidence'] );
    }

    public function testEmptyDocumentIsUnavailable(): void {
        $engine   = new ContentIntelligence();
        $analysis = $engine->analyze( '', 'welder', [ 'amperage' ] );
        $gain     = $engine->informationGain( ' ' );
        $product  = $engine->productGain( '' );

        self::assertSame( 'UNAVAILABLE', $analysis['status'] );
        self::assertNull( $analysis['word_count'] );
        self::assertNull( $analysis['keyword_density'] );
        self::assertNull( $analysis['completeness'] );
        self::assertNull( $gain['tests'] );
        self::assertNull( $gain['measurements'] );
        self::assertNull( $product['measurements'] );
        self::assertSame( 'UNAVAILABLE', $product['status'] );
    }

    public function testDensityIsSecondaryAndCitationsSeeRawLinks(): void {
        $html = '<p>A welder guide. <a href="https://example.com/spec">spec</a></p><table><tr><td>12 mm</td></tr></table>';
        $row  = ( new ContentIntelligence() )->analyze( $html, 'welder', [ 'amperage' ] );

        self::assertSame( 'MEASURED', $row['status'] );
        self::assertNull( $row['completeness'] );
        self::assertTrue( $row['citations'] );
        self::assertTrue( $row['tables'] );
        self::assertSame( [], $row['topics_covered'] );
        self::assertSame( 'MEASURED', $row['keyword_density']['provenance'] );
        self::assertStringContainsString( 'secondary', $row['keyword_density']['note'] );
    }

    public function testInformationAndProductMarkers(): void {
        $html    = '<p>We tested this welder. Original research. Case study. 20 kg. Selection guide. Application guide. Datasheet. CAD file. Manual.</p><img src="a.jpg"><blockquote>Expert</blockquote><p>Calculator dataset vs other.</p>';
        $gain    = ( new ContentIntelligence() )->informationGain( $html );
        $product = ( new ContentIntelligence() )->productGain( $html );

        self::assertTrue( $gain['tests'] );
        self::assertTrue( $gain['original_research'] );
        self::assertTrue( $gain['measurements'] );
        self::assertTrue( $gain['quotes'] );
        self::assertTrue( $product['selection_guides'] );
        self::assertTrue( $product['application_guides'] );
        self::assertTrue( $product['photos'] );
        self::assertFalse( $product['video'] );
    }

    public function testCoverageNeedsSerpText(): void {
        $engine  = new ContentIntelligence();
        $missing = $engine->coverage( '<p>amperage</p>', [ 'amperage', 'duty cycle' ], [], [] );
        $rows    = $engine->coverage(
            '<p>amperage duty cycle spares</p>',
            [ 'amperage', 'duty cycle', 'spares', 'unrelated' ],
            [ 'Amperage matters' ],
            [ 'Duty cycle and amperage' ]
        );

        self::assertNull( $missing[0]['classification'] );
        self::assertSame( 'important', $rows[0]['classification'] );
        self::assertSame( 'important', $rows[1]['classification'] );
        self::assertSame( 'optional', $rows[2]['classification'] );
        self::assertSame( 'irrelevant', $rows[3]['classification'] );
    }

    public function testEvidenceHasNoScore(): void {
        $row = ( new ContentIntelligence() )->evidence( '<p>Two year warranty and support@shop.example</p>', [] );

        self::assertNull( $row['score'] );
        self::assertSame( 'This is not a Google E-E-A-T score.', $row['note'] );
        self::assertTrue( $row['warranty'] );
        self::assertNull( $row['expertise'] );
        self::assertNull( ( new ContentIntelligence() )->evidence( '', [] )['warranty'] );
    }

    public function testEntitiesNeedADictionary(): void {
        $engine  = new ContentIntelligence();
        $missing = $engine->entities( '<p>Acme Welder</p>', [] );
        $found   = $engine->entities(
            '<p>Acme Welder in Valjevo</p>',
            [
                [
                    'name' => 'Acme',
                    'type' => 'brand',
                ],
                [
                    'name' => 'Valjevo',
                    'type' => 'location',
                ],
            ]
        );

        self::assertSame( 'UNAVAILABLE', $missing['status'] );
        self::assertNull( $missing['relationships'] );
        self::assertSame( 'MEASURED', $found['status'] );
        self::assertCount( 2, $found['mentions'] );
        self::assertSame( 'co_mentioned', $found['relationships'][0]['relation'] );
    }

    public function testLinkGraphOrphanAndCommerceSuggestion(): void {
        $engine = new ContentIntelligence();
        $graph  = $engine->linkGraph(
            [
                [
                    'url'     => 'https://shop.example/',
                    'links'   => [ 'https://shop.example/welders' ],
                    'depth'   => 0,
                    'anchors' => [ 'welders' ],
                    'broken'  => [],
                ],
                [
                    'url'   => 'https://shop.example/orphan',
                    'links' => [],
                ],
            ],
            'https://shop.example/'
        );
        $links  = $engine->commerceLinks(
            [
                [
                    'type' => 'blog',
                    'name' => 'Welder buying guide',
                    'url'  => 'https://shop.example/guide',
                ],
                [
                    'type' => 'product',
                    'name' => 'Welder 200A',
                    'url'  => 'https://shop.example/welder',
                ],
                [
                    'type' => 'product',
                    'name' => 'Gloves',
                    'url'  => 'https://shop.example/gloves',
                ],
            ]
        );
        $empty  = $engine->linkGraph( [], 'https://shop.example/' );

        self::assertSame( 'UNAVAILABLE', $empty['status'] );
        self::assertNull( $empty['orphans'] );
        self::assertContains( 'https://shop.example/orphan', $graph['orphans'] );
        self::assertNull( $graph['pages'][1]['depth'] );
        self::assertSame( [ 'welders' => 1 ], $graph['anchors'] );
        self::assertSame( [], $graph['broken'] );
        self::assertSame( 'article_to_product', $links[0]['reason'] );
        self::assertFalse( $links[0]['applied'] );
        self::assertCount( 1, $links );
    }

    public function testTopicalAuthorityDoesNotInventRank(): void {
        $row = ( new ContentIntelligence() )->topicalAuthority(
            [
                [
                    'topic'    => 'welders',
                    'products' => [ 'https://shop.example/welder' ],
                ],
            ]
        );

        self::assertNull( $row['topics'][0]['rank'] );
        self::assertNull( $row['topics'][0]['backlinks'] );
        self::assertNull( ( new ContentIntelligence() )->topicalAuthority( [] )['topics'] );
    }

    public function testStoredAnalysisKeepsScoresNull(): void {
        $database = new ArrayDatabase();
        $store    = new ContentRepository( $database );
        $store->save(
            9,
            [
                'coverage' => [],
                'entities' => [
                    'mentions' => [],
                ],
                'evidence' => [
                    'score' => null,
                ],
            ]
        );
        $store->saveEntities(
            [
                [
                    'name'     => 'Acme',
                    'type'     => 'brand',
                    'relation' => 'mentions',
                ],
                [
                    'name'     => 'Valjevo',
                    'type'     => 'location',
                    'relation' => 'mentions',
                ],
            ]
        );
        $stored = $store->latest( 9 );
        $module = new ContentModule();
        $report = $module->inspect(
            [
                'html'  => '<p>Acme welder 20 mm</p>',
                'query' => 'buy welder',
                'url'   => 'https://shop.example/should-not-be-fetched',
            ]
        );

        self::assertFalse( $report['fetched'] );
        self::assertNull( $stored['completeness'] );
        self::assertNull( $stored['information_gain'] );
        self::assertSame( 'transactional', $report['intent']['primary'] );
        self::assertCount( 1, $database->select( $database->prefix() . 'qn_entity_relations', [ 'relation_type' => 'co_mentioned' ], 5 ) );
    }

    public function testModuleDoesNotFetch(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Content/ContentModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        $engine = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Content/Application/ContentIntelligence.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wp_remote_', $source . $engine );
        self::assertStringNotContainsString( 'template_redirect', $source . $engine );
    }

    public function testSafeModeOmitsContent(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'content', $names );
        self::assertNotContains(
            'content',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
