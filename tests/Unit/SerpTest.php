<?php
/**
 * Provider-backed SERP snapshots, page types, stability, and rank history.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Serp\Application\CompetitorMatrix;
use QueryNova\Modules\Serp\Application\PageTypeClassifier;
use QueryNova\Modules\Serp\Application\SerpCapture;
use QueryNova\Modules\Serp\Application\SerpStability;
use QueryNova\Modules\Serp\Domain\SerpHit;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Infrastructure\NullSerpProvider;
use QueryNova\Modules\Serp\Infrastructure\SerpRepository;
use QueryNova\Tests\Support\FixtureSerpProvider;

final class SerpTest extends TestCase {

    public function testClassifierReadsTheReturnedUrl(): void {
        $types = new PageTypeClassifier();

        self::assertSame( 'product', $types->classify( 'https://shop.example/product/welder', 'Welder' ) );
        self::assertSame( 'category', $types->classify( 'https://shop.example/product-category/welders', 'Welders' ) );
        self::assertSame( 'article', $types->classify( 'https://example.com/blog/how-to', 'How to' ) );
        self::assertSame( 'marketplace', $types->classify( 'https://www.amazon.com/dp/123', 'Listing' ) );
        self::assertSame( 'homepage', $types->classify( 'https://example.com/', 'Home' ) );
        self::assertSame( 'forum', $types->classify( 'https://www.reddit.com/r/welding', 'Thread' ) );
        self::assertSame( 'video', $types->classify( 'https://www.youtube.com/watch?v=1', 'Video' ) );
        self::assertSame( 'local', $types->classify( 'https://example.com/locations/valjevo', 'Shop' ) );
        self::assertSame( 'comparison', $types->classify( 'https://example.com/alpha-vs-beta', 'Alpha vs Beta' ) );
        self::assertSame( 'brand', $types->classify( 'https://example.com/brands/northwind', 'Northwind' ) );
        self::assertSame( 'unknown', $types->classify( 'https://example.com/about', 'About' ) );
    }

    public function testStabilityNeedsTwoSnapshots(): void {
        $stability = new SerpStability();
        $previous  = $this->urls( 10, 0 );
        $mostly    = $previous;
        $mostly[8] = 'https://other.example/8';
        $mostly[9] = 'https://other.example/9';
        $half      = $this->urls( 5, 0 );
        for ( $i = 5; $i < 10; $i++ ) {
            $half[] = 'https://other.example/' . $i;
        }
        $few = [ 'https://example.com/0', 'https://example.com/1' ];
        for ( $i = 2; $i < 10; $i++ ) {
            $few[] = 'https://other.example/' . $i;
        }

        self::assertNull( $stability->compare( [], $previous ) );
        self::assertSame( 'stable', $stability->compare( $previous, $mostly ) );
        self::assertSame( 'moderately_volatile', $stability->compare( $previous, $half ) );
        self::assertSame( 'highly_volatile', $stability->compare( $previous, $few ) );
    }

    public function testCaptureStoresProviderResultsAndLeavesMissingAuthorityNull(): void {
        $database = new ArrayDatabase();
        $capture  = new SerpCapture( new SerpRepository( $database ) );
        $provider = new FixtureSerpProvider( $this->hits(), [ 'shopping' ] );
        $first    = $capture->capture( $this->query(), $provider );
        $second   = $capture->capture( $this->query(), $provider );
        $stored   = $database->select( 'wp_qn_competitors', [], 5 );

        self::assertSame( 'ok', $first['status'] );
        self::assertSame( 10, $first['results'] );
        self::assertNull( $first['stability'] );
        self::assertSame( 4, $first['rank'] );
        self::assertSame( 'stable', $second['stability'] );
        self::assertSame( 2, $provider->calls );
        self::assertNull( $stored[0]['authority'] );
        self::assertNull( $stored[0]['referring_domains'] );
        self::assertStringContainsString( '"backlinks":null', (string) $stored[0]['metrics_json'] );
        $rank = $database->select( 'wp_qn_rank_history', [], 5 );
        self::assertSame( 4, (int) $rank[0]['position'] );
        $snapshot = $database->select( 'wp_qn_serp_snapshots', [ 'id' => $first['snapshot_id'] ], 1 );
        self::assertSame( '["shopping"]', $snapshot[0]['features_json'] );
        $classified = $database->select( 'wp_qn_serp_results', [ 'position' => 1 ], 1 );
        self::assertSame( 'product', $classified[0]['page_type'] );
    }

    public function testNullProviderDoesNotInventResults(): void {
        $database = new ArrayDatabase();
        $capture  = new SerpCapture( new SerpRepository( $database ) );
        $result   = $capture->capture( $this->query(), new NullSerpProvider() );

        self::assertSame( 'unavailable', $result['status'] );
        self::assertNull( $result['results'] );
        self::assertNull( $result['rank'] );
        self::assertSame( [], $database->select( 'wp_qn_serp_results', [], 10 ) );
        self::assertSame( [], $database->select( 'wp_qn_rank_history', [], 10 ) );
    }

    public function testMatrixMedianAndIntentStayMeasured(): void {
        $matrix = ( new CompetitorMatrix() )->compare( $this->hits(), 'example.com' );

        self::assertSame( 4, $matrix['our_position'] );
        self::assertSame( 2.0, $matrix['top3_median'] );
        self::assertSame( 5.5, $matrix['top10_median'] );
        self::assertTrue( $matrix['intent_match'] );
        self::assertNull( $matrix['authority'] );
        self::assertNull( $matrix['eeat'] );
    }

    public function testDepthAndKeywordAreValidated(): void {
        $this->expectException( ValidationException::class );
        SerpQuery::fromArray(
            [
                'keyword' => '',
                'depth'   => 15,
            ]
        );
    }

    public function testProviderSourceDoesNotScrapeGoogle(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Serp/Infrastructure/NullSerpProvider.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        $module = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Serp/SerpModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'google.com/search', $source . $module );
        self::assertStringNotContainsString( 'wp_remote_', $module );
    }

    public function testSafeModeOmitsSerp(): void {
        $names = [];
        foreach ( ModuleCatalog::modules( false ) as $module ) {
            $names[] = $module->getName();
        }

        self::assertContains( 'serp', $names );
        self::assertNotContains(
            'serp',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }

    private function query(): SerpQuery {
        return SerpQuery::fromArray(
            [
                'keyword_id' => 3,
                'keyword'    => 'welder',
                'country'    => 'us',
                'language'   => 'en',
                'device'     => 'desktop',
                'depth'      => 10,
                'domain'     => 'www.example.com',
            ]
        );
    }

    /**
     * @return list<SerpHit>
     */
    private function hits(): array {
        $hits = [];
        for ( $i = 1; $i <= 12; $i++ ) {
            $domain = $i === 4 ? 'www.example.com' : 'rival-' . $i . '.example';
            $hits[] = new SerpHit(
                $i,
                'https://' . $domain . '/product/item-' . $i,
                $domain,
                'Item ' . $i,
                'Snippet ' . $i,
                $i === 1 ? '' : 'product',
                null
            );
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    private function urls( int $count, int $offset ): array {
        $urls = [];
        for ( $i = $offset; $i < $offset + $count; $i++ ) {
            $urls[] = 'https://example.com/' . $i;
        }

        return $urls;
    }
}
