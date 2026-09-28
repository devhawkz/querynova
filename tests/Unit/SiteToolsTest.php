<?php
/**
 * Schema studio, sitemap channels, and site tools that do not write without permission.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Content\Application\LinkBoard;
use QueryNova\Modules\Content\ContentModule;
use QueryNova\Modules\Redirects\Application\PermalinkRedirect;
use QueryNova\Modules\Redirects\Application\RedirectList;
use QueryNova\Modules\Redirects\RedirectModule;
use QueryNova\Modules\Schema\Application\JsonLdStudio;
use QueryNova\Modules\Schema\SchemaModule;
use QueryNova\Modules\Seo\Application\Breadcrumbs;
use QueryNova\Modules\Seo\Application\HtaccessEditor;
use QueryNova\Modules\Seo\Application\ImageAlt;
use QueryNova\Modules\Seo\Application\IndexNowPlan;
use QueryNova\Modules\Seo\Application\RobotsEditor;
use QueryNova\Modules\Seo\Application\RssSupplement;
use QueryNova\Modules\Seo\Application\WebmasterCodes;
use QueryNova\Modules\Seo\SeoModule;
use QueryNova\Modules\Sitemap\Application\SitemapChannels;
use QueryNova\Modules\Sitemap\SitemapModule;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.unlink_unlink -- tests read and remove temporary files.

final class SiteToolsTest extends TestCase {

    protected function tearDown(): void {
        unset(
            $GLOBALS['querynova_options'][ SitemapChannels::OPTION ],
            $GLOBALS['querynova_options'][ RobotsEditor::OPTION ],
            $GLOBALS['querynova_options'][ HtaccessEditor::OPTION ],
            $GLOBALS['querynova_options'][ WebmasterCodes::OPTION ],
            $GLOBALS['querynova_options'][ RssSupplement::OPTION ],
            $GLOBALS['querynova_options'][ Breadcrumbs::OPTION ],
            $GLOBALS['querynova_options']['querynova_local_seo_enabled'],
            $GLOBALS['querynova_options']['querynova_sitemap_news_name']
        );
        parent::tearDown();
    }

    public function testAMissingSitemapOptionLeavesTheCurrentTypeListUnchanged(): void {
        $current = [ 'post', 'page', 'product', 'news' ];

        self::assertSame( $current, SitemapChannels::limit( $current, false, '' ) );
        self::assertArrayNotHasKey( SitemapChannels::OPTION, $GLOBALS['querynova_options'] );
    }

    public function testNewsAndKmlStayOffUntilConfiguredAndAreNotAddedToXml(): void {
        $saved = SitemapChannels::save(
            [
                'channels' => [
                    'news'   => true,
                    'kml'    => true,
                    'author' => true,
                    'post'   => false,
                ],
            ],
            false,
            ''
        );

        self::assertFalse( $saved['channels']['news'] );
        self::assertFalse( $saved['channels']['kml'] );
        self::assertFalse( $saved['channels']['post'] );
        self::assertSame( [ 'page' ], SitemapChannels::limit( [ 'post', 'page', 'news' ], false, '' ) );

        SitemapChannels::save(
            [
                'channels' => [
                    'news' => true,
                    'kml'  => true,
                ],
            ],
            true,
            'Valjevo News'
        );
        $open = SitemapChannels::limit( [ 'post', 'news' ], true, 'Valjevo News' );

        self::assertContains( 'news', $open );
        self::assertNotContains( 'kml', $open );
        self::assertNotContains( 'author', $open );
        self::assertSame(
            '',
            SitemapChannels::kml(
                [
                    [
                        'name'      => 'Taproom',
                        'latitude'  => 44.27,
                        'longitude' => 19.88,
                    ],
                ],
                false
            )['kml']
        );
        self::assertStringContainsString( 'local SEO', SitemapChannels::kml( [], false )['note'] );
    }

    public function testHtmlSitemapSkipsNonHttpUrls(): void {
        $html = SitemapChannels::html( [ 'https://example.test/lager', 'javascript:alert(1)', '/relative' ] );

        self::assertStringContainsString( 'querynova-html-sitemap', $html );
        self::assertStringContainsString( 'https://example.test/lager', $html );
        self::assertStringNotContainsString( 'javascript:', $html );
        self::assertStringNotContainsString( '/relative', $html );
    }

    public function testJsonLdImportShowsOnlySuppliedPropertiesAndDoesNotSave(): void {
        $before  = $GLOBALS['querynova_options']['querynova_schema_rules'] ?? null;
        $preview = JsonLdStudio::import( '{"@type":"Article","headline":"Lager","description":"","image":""}' );

        self::assertTrue( $preview['valid'] );
        self::assertFalse( $preview['saved'] );
        self::assertIsArray( $preview['rule'] );
        self::assertCount( 1, $preview['rule']['mappings'] );
        self::assertSame( 'headline', $preview['rule']['mappings'][0]['property'] );
        self::assertSame( [ 'article', 'product', 'breadcrumb' ], array_column( JsonLdStudio::templates(), 'id' ) );

        $rejected = JsonLdStudio::import( '{"@type":"FAQPage","name":"Questions"}' );

        self::assertFalse( $rejected['valid'] );
        self::assertNull( $rejected['rule'] );
        self::assertFalse( $rejected['saved'] );
        self::assertSame( $before, $GLOBALS['querynova_options']['querynova_schema_rules'] ?? null );
    }

    public function testValidationDoesNotSaveAndRequiresATypeAndId(): void {
        $invalid = JsonLdStudio::validate(
            [
                'type'        => 'FAQPage',
                'id_template' => '',
            ]
        );

        self::assertFalse( $invalid['valid'] );
        self::assertFalse( $invalid['saved'] );
        self::assertContains( 'Choose a supported schema type.', $invalid['errors'] );
        self::assertContains( 'An @id template is required.', $invalid['errors'] );
    }

    public function testEmptyWebmasterCodesPrintNothing(): void {
        self::assertSame( '', WebmasterCodes::meta( WebmasterCodes::read() ) );
        self::assertArrayNotHasKey( WebmasterCodes::OPTION, $GLOBALS['querynova_options'] );
        $codes = WebmasterCodes::save(
            [
                'google' => 'abc',
                'bing'   => '',
            ]
        );

        self::assertStringContainsString( 'google-site-verification', WebmasterCodes::meta( $codes ) );
        self::assertStringNotContainsString( 'bing-site-verification', WebmasterCodes::meta( $codes ) );
    }

    public function testManualAltIsLeftUnchanged(): void {
        $result = ImageAlt::suggest( 'Taproom glass', 'A generated caption', true, false );

        self::assertSame( 'Taproom glass', $result['alt'] );
        self::assertFalse( $result['changed'] );
        self::assertFalse( $result['written'] );
        self::assertStringContainsString( 'Manual ALT', $result['note'] );
    }

    public function testRobotsSaveWithoutPermissionDoesNotCreateAFile(): void {
        $path  = $this->tempPath( 'robots' );
        $saved = RobotsEditor::save( "User-agent: *\nDisallow: /private", false, false, $path );

        self::assertFalse( $saved['file_written'] );
        self::assertFileDoesNotExist( $path );

        file_put_contents( $path, "old\n" );
        $kept = RobotsEditor::save( 'new', true, true, '' );

        self::assertFalse( $kept['file_written'] );
        self::assertSame( "old\n", (string) file_get_contents( $path ) );
        unlink( $path );
    }

    public function testConfirmedRobotsWriteReplacesOnlyTheSuppliedFile(): void {
        $path = $this->tempPath( 'robots-write' );
        file_put_contents( $path, 'old' );
        $written = RobotsEditor::save( 'User-agent: *', true, true, $path );

        self::assertTrue( $written['file_written'] );
        self::assertSame( 'User-agent: *', (string) file_get_contents( $path ) );
        unlink( $path );
    }

    public function testHtaccessStaysHiddenWithoutBackupUntilConfirmed(): void {
        $path = $this->tempPath( 'htaccess' );
        file_put_contents( $path, "old\n" );

        self::assertFalse( HtaccessEditor::visibility( 'nginx/1.24', true )['visible'] );
        self::assertFalse( HtaccessEditor::visibility( 'Apache', false )['visible'] );
        $hidden = HtaccessEditor::save( 'new', 'nginx/1.24', true, true, true, $path );
        self::assertFalse( $hidden['written'] );
        self::assertNull( $hidden['backup'] );
        self::assertSame( "old\n", (string) file_get_contents( $path ) );

        $unconfirmed = HtaccessEditor::save( 'new', 'Apache', true, false, true, $path );
        self::assertFalse( $unconfirmed['written'] );
        self::assertSame( "old\n", (string) file_get_contents( $path ) );

        $backup = HtaccessEditor::save( 'new', 'Apache', true, true, false, $path );
        self::assertFalse( $backup['written'] );
        self::assertSame( "old\n", $backup['backup'] );
        self::assertSame( "old\n", (string) file_get_contents( $path ) );
        unlink( $path );
    }

    public function testIndexNowSkipsNoindexAndDoesNotSend(): void {
        $plan = IndexNowPlan::plan(
            [ 'https://example.test/a', 'https://example.test/b', 'ftp://example.test/c' ],
            [ 'https://example.test/b' ]
        );

        self::assertSame( [ 'https://example.test/a' ], $plan['queued'] );
        self::assertSame( [ 'https://example.test/b' ], $plan['skipped'] );
        self::assertFalse( $plan['requested'] );
    }

    public function testRssWrapIsIdentityUntilEnabled(): void {
        self::assertSame( 'body', RssSupplement::wrap( 'body' ) );
        RssSupplement::save(
            [
                'enabled' => false,
                'before'  => 'Before ',
                'after'   => ' after',
            ]
        );
        self::assertSame( 'body', RssSupplement::wrap( 'body' ) );
        RssSupplement::save(
            [
                'enabled' => true,
                'before'  => 'Before ',
                'after'   => ' after',
            ]
        );
        self::assertSame( 'Before body after', RssSupplement::wrap( 'body' ) );
    }

    public function testBreadcrumbsRenderHtmlAndBreadcrumbList(): void {
        self::assertSame( '', Breadcrumbs::html( [] ) );
        $html = Breadcrumbs::html(
            [
                [
                    'label' => 'Home',
                    'url'   => 'https://example.test/',
                ],
                [
                    'label' => 'Lager',
                    'url'   => '',
                ],
            ]
        );

        self::assertStringContainsString( 'aria-label="Breadcrumb"', $html );
        self::assertStringContainsString( 'https://example.test/', $html );
        self::assertStringContainsString( 'Lager', $html );
        $schema = Breadcrumbs::schema(
            [
                [
                    'label' => 'Home',
                    'url'   => 'https://example.test/',
                ],
            ]
        );
        self::assertSame( 'BreadcrumbList', $schema['@type'] );
        self::assertSame( 'ListItem', $schema['itemListElement'][0]['@type'] );

        require_once dirname( __DIR__, 2 ) . '/src/Modules/Seo/breadcrumbs-function.php';
        self::assertSame( '', querynova_breadcrumbs( [] ) );
    }

    public function testPermalinkRedirectWaitsForConfirmation(): void {
        $waiting = PermalinkRedirect::plan( '/old-lager', '/lager', false );

        self::assertFalse( $waiting['created'] );
        self::assertStringContainsString( 'confirm', $waiting['note'] );

        $ready = PermalinkRedirect::plan( '/old-lager', '/lager', true );

        self::assertTrue( $ready['created'] );
        self::assertSame( 301, $ready['status'] );
    }

    public function testRedirectListSearchesFiltersAndPaginates(): void {
        $rows  = [
            [
                'source' => '/a',
                'target' => '/b',
                'status' => 301,
            ],
            [
                'source' => '/c',
                'target' => '/d',
                'status' => 302,
            ],
            [
                'url'    => 'https://example.test/missing-lager',
                'status' => '404',
            ],
        ];
        $found = RedirectList::slice( $rows, 'missing-lager', '', 1, 20 );

        self::assertSame( 1, $found['total'] );
        self::assertSame( 'https://example.test/missing-lager', $found['rows'][0]['url'] );

        $status = RedirectList::slice( $rows, '', '301', 1, 1 );

        self::assertSame( 1, $status['total'] );
        self::assertSame( '/a', $status['rows'][0]['source'] );
        self::assertSame( 1, $status['pages'] );
    }

    public function testLinkSuggestionsStaySuggestOnly(): void {
        $board = LinkBoard::present(
            [
                [
                    'url'    => 'https://example.test/a',
                    'links'  => [],
                    'broken' => [ 'https://example.test/gone' ],
                ],
            ],
            [
                [
                    'type' => 'product',
                    'name' => 'Lager bottle',
                    'url'  => 'https://example.test/lager',
                ],
                [
                    'type' => 'category',
                    'name' => 'Lager',
                    'url'  => 'https://example.test/category/lager',
                ],
            ]
        );

        self::assertSame( 'Suggest Only', $board['mode'] );
        self::assertFalse( $board['inserted'] );
        self::assertNotEmpty( $board['suggestions'] );
        foreach ( $board['suggestions'] as $suggestion ) {
            self::assertFalse( $suggestion['applied'] );
            self::assertSame( 'Suggest Only', $suggestion['mode'] );
        }

        $empty = LinkBoard::present( [] );
        self::assertNull( $empty['broken'] );
        self::assertNull( $empty['orphans'] );
        self::assertFalse( $empty['inserted'] );
    }

    public function testRoutesAreRegisteredOnTheExistingModules(): void {
        $rest = new RestRegistrar();
        ( new SeoModule() )->registerRoutes( $rest );
        ( new SitemapModule() )->registerRoutes( $rest );
        ( new SchemaModule() )->registerRoutes( $rest );
        ( new ContentModule() )->registerRoutes( $rest );
        ( new RedirectModule() )->registerRoutes( $rest );
        $names = array_map(
            static fn ( array $route ): string => $route['method'] . ' ' . $route['route'],
            $rest->routes()
        );

        foreach (
            [
                'GET /schema/templates',
                'POST /schema/jsonld',
                'POST /schema/preview',
                'GET /sitemap/channels',
                'PUT /sitemap/channels',
                'POST /sitemap/html',
                'POST /sitemap/kml',
                'POST /seo/image-alt',
                'POST /content/links',
                'GET /content/links',
                'GET /redirects/list',
                'GET /not-found/list',
                'POST /redirects/permalink',
                'PUT /seo/robots',
                'PUT /seo/htaccess',
                'PUT /seo/webmaster',
                'PUT /seo/rss',
                'PUT /seo/breadcrumbs',
                'POST /seo/breadcrumbs/preview',
                'POST /seo/indexnow',
            ] as $route
        ) {
            self::assertContains( $route, $names );
        }
    }

    public function testSiteToolsDoNotCallTheNetworkOrAChartLibrary(): void {
        $files = [
            dirname( __DIR__, 2 ) . '/src/Modules/Seo/Application/IndexNowPlan.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Seo/Application/ImageAlt.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Seo/Application/RobotsEditor.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Seo/Application/HtaccessEditor.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Seo/Presentation/SiteToolsController.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Seo/Presentation/SiteHead.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Schema/Application/JsonLdStudio.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Content/Application/LinkBoard.php',
            dirname( __DIR__, 2 ) . '/querynova.php',
            dirname( __DIR__, 2 ) . '/package.json',
        ];
        foreach ( $files as $file ) {
            $source = (string) file_get_contents( $file );
            self::assertStringNotContainsString( 'wp_remote_', $source );
            self::assertStringNotContainsString( 'api.indexnow.org', $source );
            self::assertStringNotContainsString( 'chart.js', strtolower( $source ) );
        }
        $plugin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/querynova.php' );
        $head   = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Modules/Seo/Presentation/SiteHead.php' );
        self::assertStringContainsString( 'breadcrumbs-function.php', $plugin );
        self::assertStringContainsString( 'querynova_breadcrumbs', $head );
        self::assertStringContainsString( 'querynova/breadcrumbs', $head );
    }

    private function tempPath( string $name ): string {
        return sys_get_temp_dir() . '/querynova-' . $name . '-' . uniqid( '', true ) . '.txt';
    }
}
