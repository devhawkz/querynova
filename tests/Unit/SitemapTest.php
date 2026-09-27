<?php
/**
 * Sitemap inclusion, paging, and XML tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Cache\MemoryCache;
use QueryNova\Modules\Sitemap\Application\EnabledChannels;
use QueryNova\Modules\Sitemap\Application\RobotsSitemapLine;
use QueryNova\Modules\Sitemap\Application\SitemapBuilder;
use QueryNova\Modules\Sitemap\Application\SitemapRenderer;
use QueryNova\Modules\Sitemap\Application\SitemapSettings;
use QueryNova\Modules\Sitemap\Application\SitemapXml;
use QueryNova\Modules\Sitemap\Domain\MediaExtractor;
use QueryNova\Modules\Sitemap\Domain\SitemapCandidate;
use QueryNova\Modules\Sitemap\Domain\SitemapPolicy;
use QueryNova\Modules\Sitemap\Infrastructure\ArrayContentCatalog;

final class SitemapTest extends TestCase {

    private \DateTimeImmutable $now;

    protected function setUp(): void {
        $this->now                    = new \DateTimeImmutable( '2026-09-27T12:00:00+00:00' );
        $GLOBALS['querynova_options'] = [];
    }

    public function testPolicyDropsPrivatePasswordAndNoindexUrls(): void {
        $policy = new SitemapPolicy( 'Northwind News' );

        self::assertFalse( $policy->allows( $this->candidate( [ 'status' => 'draft' ] ), 'post', $this->now ) );
        self::assertFalse( $policy->allows( $this->candidate( [ 'visibility' => 'private' ] ), 'post', $this->now ) );
        self::assertFalse( $policy->allows( $this->candidate( [ 'password' => true ] ), 'post', $this->now ) );
        self::assertFalse( $policy->allows( $this->candidate( [ 'indexable' => false ] ), 'post', $this->now ) );
        self::assertFalse( $policy->allows( $this->candidate( [ 'url' => 'javascript:alert(1)' ] ), 'post', $this->now ) );
    }

    public function testCanonicalMustPointAtTheSameUrl(): void {
        $policy = new SitemapPolicy();

        self::assertTrue( $policy->allows( $this->candidate(), 'post', $this->now ) );
        self::assertTrue(
            $policy->allows(
                $this->candidate(
                    [
                        'url'       => 'https://example.test/post/',
                        'canonical' => 'HTTPS://Example.Test/post',
                    ]
                ),
                'post',
                $this->now
            )
        );
        self::assertFalse(
            $policy->allows( $this->candidate( [ 'canonical' => 'https://other.test/post' ] ), 'post', $this->now )
        );
    }

    public function testNewsIsLimitedToFortyEightHoursAndAPublicationName(): void {
        $recent = $this->candidate(
            [
                'newsAt'    => '2026-09-26T12:00:00+00:00',
                'newsTitle' => 'Launch day',
            ]
        );
        $stale  = $this->candidate(
            [
                'newsAt'    => '2026-09-25T11:00:00+00:00',
                'newsTitle' => 'Old story',
            ]
        );

        self::assertTrue( ( new SitemapPolicy( 'Northwind News' ) )->allows( $recent, 'news', $this->now ) );
        self::assertFalse( ( new SitemapPolicy( 'Northwind News' ) )->allows( $stale, 'news', $this->now ) );
        self::assertFalse( ( new SitemapPolicy() )->allows( $recent, 'news', $this->now ) );
    }

    public function testImageAndVideoChannelsRequireMedia(): void {
        $policy = new SitemapPolicy();
        $plain  = $this->candidate();
        $rich   = $this->candidate(
            [
                'images' => [ 'https://example.test/photo.jpg' ],
                'videos' => [
                    [
                        'url'       => 'https://example.test/clip.mp4',
                        'title'     => 'Clip',
                        'thumbnail' => '',
                    ],
                ],
            ]
        );

        self::assertFalse( $policy->allows( $plain, 'image', $this->now ) );
        self::assertTrue( $policy->allows( $rich, 'image', $this->now ) );
        self::assertTrue( $policy->allows( $rich, 'video', $this->now ) );
        self::assertFalse( $policy->allows( $rich, 'page', $this->now ) );
    }

    public function testIndexPagesAtOneThousandAndSkipsEmptyOrDisabledChannels(): void {
        $candidates = [];
        for ( $index = 1; $index <= 5; $index++ ) {
            $candidates[] = $this->candidate( [ 'url' => 'https://example.test/post-' . $index ] );
        }
        $catalog = new ArrayContentCatalog( $candidates, [ 'post', 'product', 'news' ] );
        $xml     = ( new SitemapBuilder( new SitemapPolicy(), new SitemapXml(), 2 ) )->index(
            $catalog,
            'https://example.test',
            [ 'post', 'page' ]
        );

        self::assertSame( 3, substr_count( $xml, '<loc>' ) );
        self::assertStringContainsString( 'https://example.test/querynova-sitemap-post-3.xml', $xml );
        self::assertStringNotContainsString( 'querynova-sitemap-product-', $xml );
        self::assertStringNotContainsString( 'querynova-sitemap-news-', $xml );
    }

    public function testUrlsetEscapesTextAndDropsExcludedUrls(): void {
        $catalog = new ArrayContentCatalog(
            [
                $this->candidate( [ 'url' => 'https://example.test/a?b=1&c=2' ] ),
                $this->candidate(
                    [
                        'url'       => 'https://example.test/hidden',
                        'indexable' => false,
                    ]
                ),
            ],
            [ 'post' ]
        );
        $xml     = ( new SitemapBuilder( new SitemapPolicy(), new SitemapXml() ) )->urlset(
            $catalog,
            'post',
            1,
            $this->now,
            '',
            'en'
        );

        self::assertStringContainsString( 'https://example.test/a?b=1&amp;c=2', $xml );
        self::assertStringNotContainsString( 'https://example.test/hidden', $xml );
        self::assertStringContainsString( '<urlset ', $xml );
    }

    public function testNewsUrlsetIncludesPublicationData(): void {
        $catalog = new ArrayContentCatalog(
            [
                $this->candidate(
                    [
                        'newsAt'    => '2026-09-27T08:00:00+00:00',
                        'newsTitle' => 'A & B <launch>',
                    ]
                ),
            ],
            [ 'news' ]
        );
        $xml     = ( new SitemapBuilder( new SitemapPolicy( 'Northwind News' ), new SitemapXml() ) )->urlset(
            $catalog,
            'news',
            1,
            $this->now,
            'Northwind News',
            'en'
        );

        self::assertStringContainsString( 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $xml );
        self::assertStringContainsString( 'A &amp; B &lt;launch&gt;', $xml );
        self::assertStringContainsString( '<news:name>Northwind News</news:name>', $xml );
    }

    public function testRendererCachesTheIndexAndRejectsUnknownTypes(): void {
        $cache    = new MemoryCache();
        $renderer = new SitemapRenderer(
            new SitemapBuilder( new SitemapPolicy(), new SitemapXml() ),
            new ArrayContentCatalog( [ $this->candidate() ], [ 'post' ] ),
            $cache,
            4,
            [ 'post' ],
            '',
            'en'
        );
        $cache->set( 'sitemap:4:index:0', 'CACHED', 900 );

        self::assertSame( 'CACHED', $renderer->index( 'https://example.test' ) );
        self::assertNull( $renderer->urlset( '../etc', 1, $this->now ) );
        self::assertNull( $renderer->urlset( 'product', 1, $this->now ) );
    }

    public function testMediaExtractorKeepsHttpMediaOnly(): void {
        $extractor = new MediaExtractor();
        $html      = '<img src="https://example.test/a.jpg"><img src="javascript:alert(1)"><img src="https://example.test/a.jpg">';
        $html     .= '<video src="https://example.test/clip.mp4"></video> https://youtu.be/abcdefghijk';

        self::assertSame( [ 'https://example.test/a.jpg' ], $extractor->images( $html ) );
        $videos = $extractor->videos( $html, 'Demo' );
        self::assertSame( 'https://example.test/clip.mp4', $videos[0]['url'] );
        self::assertSame( 'https://youtu.be/abcdefghijk', $videos[1]['url'] );
        self::assertSame( 'Demo', $videos[1]['title'] );
    }

    public function testNewsChannelStaysOffWithoutANameOrFlag(): void {
        $features = $this->features();
        $channels = new EnabledChannels();
        $site     = new WordPressEnvironment();

        self::assertNotContains( 'news', $channels->types( $features, $site, 'Northwind News' ) );

        $features->overrideForSite( 'querynova.sitemap.news', FeatureFlagState::On );
        self::assertNotContains( 'news', $channels->types( $features, $site, '' ) );
        self::assertContains( 'news', $channels->types( $features, $site, 'Northwind News' ) );

        $features->overrideForSite( 'querynova.sitemap', FeatureFlagState::Off );
        self::assertNotContains( 'news', $channels->types( $features, $site, 'Northwind News' ) );
    }

    public function testSettingsNormalizeLanguageAndBumpGeneration(): void {
        $settings = new SitemapSettings();
        $GLOBALS['querynova_options'][ SitemapSettings::NEWS_LANGUAGE ] = 'not a tag';
        self::assertSame( 'en', $settings->newsLanguage() );

        $GLOBALS['querynova_options'][ SitemapSettings::NEWS_LANGUAGE ] = 'sr-RS';
        self::assertSame( 'sr-RS', $settings->newsLanguage() );
        $GLOBALS['querynova_options'][ SitemapSettings::NEWS_NAME ] = ' <b>Northwind</b> ';
        self::assertSame( 'Northwind', $settings->newsName() );

        $settings->bumpGeneration();
        $settings->bumpGeneration();
        self::assertSame( 2, $settings->generation() );
    }

    public function testRobotsLineIsAddedOnlyForAPublicEnabledSite(): void {
        $line = new RobotsSitemapLine();

        self::assertSame(
            "User-agent: *\nSitemap: https://example.test/querynova-sitemap.xml\n",
            $line->append( "User-agent: *\n", 'https://example.test/querynova-sitemap.xml', true, true )
        );
        self::assertSame( 'User-agent: *', $line->append( 'User-agent: *', 'https://example.test/querynova-sitemap.xml', false, true ) );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function candidate( array $overrides = [] ): SitemapCandidate {
        $videos = $overrides['videos'] ?? [];
        $images = $overrides['images'] ?? [];

        return new SitemapCandidate(
            is_string( $overrides['url'] ?? null ) ? $overrides['url'] : 'https://example.test/post',
            is_string( $overrides['channel'] ?? null ) ? $overrides['channel'] : 'post',
            is_string( $overrides['status'] ?? null ) ? $overrides['status'] : 'publish',
            is_string( $overrides['visibility'] ?? null ) ? $overrides['visibility'] : 'public',
            (bool) ( $overrides['password'] ?? false ),
            array_key_exists( 'indexable', $overrides ) ? (bool) $overrides['indexable'] : true,
            is_string( $overrides['canonical'] ?? null ) ? $overrides['canonical'] : '',
            '2026-09-27T00:00:00+00:00',
            is_array( $images ) ? $images : [],
            is_array( $videos ) ? $videos : [],
            is_string( $overrides['newsAt'] ?? null ) ? $overrides['newsAt'] : '',
            is_string( $overrides['newsTitle'] ?? null ) ? $overrides['newsTitle'] : ''
        );
    }

    private function features(): FeatureRegistry {
        $registry = new FeatureRegistry();
        $registry->register(
            new Feature(
                'querynova.sitemap',
                'XML sitemaps',
                'sitemap',
                '0.1.0',
                [],
                LicenseTier::Free,
                [],
                FeatureFlagState::On,
                [ Capability::MANAGE_SEO ]
            )
        );
        $registry->register(
            new Feature(
                'querynova.sitemap.news',
                'News sitemap',
                'sitemap',
                '0.1.0',
                [ 'querynova.sitemap' ],
                LicenseTier::Free,
                [],
                FeatureFlagState::Off,
                [ Capability::MANAGE_SEO ]
            )
        );

        return $registry;
    }
}
