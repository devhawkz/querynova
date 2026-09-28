<?php
/**
 * On-page checklist, title templates, and the stored SEO audit.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Seo\Application\MetaDefaults;
use QueryNova\Modules\Seo\Application\OnPageChecklist;
use QueryNova\Modules\Seo\Application\SeoAnalyzer;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Application\TitleTemplates;
use QueryNova\Modules\Seo\Domain\RobotsDirective;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;
use QueryNova\Modules\Seo\Presentation\OnPageController;
use QueryNova\Modules\Seo\SeoModule;

final class OnPageTest extends TestCase {

    protected function tearDown(): void {
        unset(
            $GLOBALS['querynova_options'][ MetaDefaults::OPTION ],
            $GLOBALS['querynova_options'][ OnPageController::AUDIT_OPTION ]
        );
        parent::tearDown();
    }

    public function testChecklistReportsStatusesWithoutAScore(): void {
        $before = $GLOBALS['querynova_options'];
        $html   = '<p>one two three four five six seven eight nine ten eleven twelve</p>';
        $report = OnPageChecklist::assess(
            [
                'title'        => '',
                'description'  => '',
                'robots_index' => 'noindex',
                'html'         => $html,
                'url'          => 'https://evil.test/should-not-be-fetched',
            ]
        );

        self::assertSame( 'On-page checklist', $report['name'] );
        self::assertSame( 'This checklist does not predict rankings.', $report['note'] );
        self::assertArrayNotHasKey( 'score', $report );
        self::assertSame( 'failed', $this->check( $report, 'title' )['status'] );
        self::assertSame( 'warning', $this->check( $report, 'content' )['status'] );
        self::assertStringContainsString( 'Word count is 12.', $this->check( $report, 'content' )['evidence'] );
        self::assertSame( 'warning', $this->check( $report, 'robots_index' )['status'] );
        self::assertSame( 'info', $this->check( $report, 'readability' )['status'] );
        self::assertStringContainsString( 'does not predict rankings', $this->check( $report, 'readability' )['explanation'] );
        self::assertSame( $before, $GLOBALS['querynova_options'] );
    }

    public function testFocusKeywordsAndAdvancedRobotsStayObservations(): void {
        $report = OnPageChecklist::assess(
            [
                'title'                    => 'Valjevo lager guide for the taproom',
                'description'              => 'A short guide to the Valjevo lager, the malt, and the taproom pour for visitors.',
                'permalink'                => 'https://example.test/valjevo-lager',
                'focus_keywords'           => 'lager, pilsner, malt, hops, yeast, water',
                'robots_max_image_preview' => 'huge',
                'html'                     => '<h1>Lager</h1><p>The lager is poured cold.</p><img src="glass.jpg"><a href="/lager">Lager</a>',
            ]
        );

        self::assertSame( 'warning', $this->check( $report, 'focus_keywords' )['status'] );
        self::assertStringContainsString( 'lager: title, description, content', $this->check( $report, 'focus_keywords' )['evidence'] );
        self::assertStringContainsString( 'pilsner: not found', $this->check( $report, 'focus_keywords' )['evidence'] );
        self::assertSame( 'failed', $this->check( $report, 'max_image_preview' )['status'] );
		self::assertSame( 'warning', $this->check( $report, 'media' )['status'] );
		self::assertStringContainsString( 'does not overwrite manual ALT', $this->check( $report, 'media' )['how_to_fix'] );
        self::assertSame( 'passed', $this->check( $report, 'links' )['status'] );
        self::assertSame( 'info', $this->check( $report, 'schema' )['status'] );
    }

    public function testInvalidImagePreviewDoesNotSaveTheTitle(): void {
        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        try {
            $service->save(
                'post',
                9,
                [
                    SeoMetaService::TITLE => 'Keep me off the store',
                    SeoMetaService::ROBOTS_MAX_IMAGE_PREVIEW => 'huge',
                ]
            );
            self::fail( 'Expected the image preview to be rejected.' );
        } catch ( ValidationException $exception ) {
            self::assertSame( 'Image preview must be empty, none, standard, or large.', $exception->getMessage() );
        }
        self::assertSame( '', $service->stored( 'post', 9, SeoMetaService::TITLE ) );
        self::assertSame( '', $service->stored( 'post', 9, SeoMetaService::ROBOTS_MAX_IMAGE_PREVIEW ) );
    }

    public function testAdvancedRobotsAppendOnlyWhenSet(): void {
        $robots = new RobotsDirective( true, true, '-1', 'large', '0' );

        self::assertSame( 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:0', $robots->content() );
        self::assertSame( 'index, follow', ( new RobotsDirective( true, true ) )->content() );

        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        $service->save(
            'post',
            3,
            [
                SeoMetaService::ROBOTS_MAX_SNIPPET       => '-1',
                SeoMetaService::ROBOTS_MAX_IMAGE_PREVIEW => 'large',
                SeoMetaService::ROBOTS_MAX_VIDEO_PREVIEW => '0',
            ]
        );
        $document = $service->resolve(
            'post',
            3,
            [
                'title'    => 'Guide',
                'sep'      => '-',
                'sitename' => 'Northwind',
                'excerpt'  => '',
            ],
            []
        );

        self::assertSame( 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:0', $document->robots()->content() );
    }

    public function testTitleTemplatesCoverTheCatalogAndRenderALocation(): void {
        self::assertSame(
            [
                'homepage',
                'post',
                'page',
                'product',
                'product_category',
                'tag',
                'category',
                'author',
                'archive',
                'cpt',
                'taxonomy',
                'location',
            ],
            TitleTemplates::contexts()
        );
        self::assertSame( TitleTemplates::contexts(), array_keys( TitleTemplates::defaults() ) );
        self::assertSame( '%%title%% %%sep%% %%sitename%%', TitleTemplates::defaults()['post']['title'] );
        self::assertContains( 'location', TitleTemplates::variables() );

        $rendered = TitleTemplates::render(
            new TemplateRenderer(),
            TitleTemplates::defaults(),
            'location',
            [
                'location' => 'Valjevo',
                'sep'      => '-',
                'sitename' => 'Northwind',
                'excerpt'  => '',
            ]
        );

        self::assertSame( 'Valjevo - Northwind', $rendered['title'] );
    }

    public function testMetaDefaultsSaveOneOptionAndLeaveStoredTitlesAlone(): void {
        unset( $GLOBALS['querynova_options'][ MetaDefaults::OPTION ] );
        $read = MetaDefaults::read();

        self::assertSame( '-', $read['separator'] );
        self::assertSame( '%%title%% %%sep%% %%sitename%%', $read['templates']['post']['title'] );

        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        $store->set( 'post', 8, SeoMetaService::TITLE, 'Custom title' );
        $saved = MetaDefaults::save(
            [
                'separator' => '|',
                'templates' => [
                    'post' => [
                        'title'       => '%%title%%',
                        'description' => '%%excerpt%%',
                    ],
                ],
                'object_id' => 8,
                'posts'     => [ 8 ],
            ]
        );

        self::assertSame( '|', $saved['separator'] );
        self::assertSame( '%%title%%', $saved['templates']['post']['title'] );
        self::assertSame( 'Custom title', $service->stored( 'post', 8, SeoMetaService::TITLE ) );
        self::assertArrayNotHasKey( 'posts', $GLOBALS['querynova_options'][ MetaDefaults::OPTION ] );
    }

    public function testAnalyzerMapsStoredIssuesAndDoesNotPlanAFetch(): void {
        $report = SeoAnalyzer::report(
            [
                [
                    'code'     => 'missing_title',
                    'severity' => 'warning',
                    'title'    => 'Missing title',
                    'detail'   => 'No title element was found.',
                    'url'      => 'https://example.test/a',
                ],
                [
                    'code'     => 'http_error',
                    'severity' => 'error',
                    'title'    => 'HTTP error',
                    'detail'   => '500',
                    'url'      => 'https://example.test/b',
                ],
            ]
        );

        self::assertSame( 'SEO Analyzer', $report['name'] );
        self::assertSame( 'These findings do not estimate ranking impact.', $report['note'] );
        self::assertArrayNotHasKey( 'score', $report );
        self::assertSame( 'warning', $report['findings'][0]['status'] );
        self::assertSame( 'Add a title element. This audit does not rewrite titles.', $report['findings'][0]['how_to_fix'] );
        self::assertSame( 'failed', $report['findings'][1]['status'] );

        $clear = SeoAnalyzer::report( [], 3 );
        self::assertSame( 'passed', $clear['findings'][0]['status'] );
        self::assertSame( 'stored_crawl_clear', $clear['findings'][0]['code'] );

        $empty = SeoAnalyzer::report( [] );
        self::assertSame( 'info', $empty['findings'][0]['status'] );
        self::assertSame( 'no_stored_crawl', $empty['findings'][0]['code'] );

        $plan = SeoAnalyzer::plan( 'seo-audit-test' );
        self::assertSame( [ 'source' => 'stored' ], $plan['payload'] );
        self::assertArrayNotHasKey( 'url', $plan['payload'] );
        self::assertSame( 'This request does not crawl the site.', $plan['note'] );
        $encoded = wp_json_encode( $plan );
        self::assertIsString( $encoded );
        self::assertStringNotContainsString( 'fetch', $encoded );
        self::assertStringNotContainsString( 'http', $encoded );
    }

    public function testAuditRoutesQueueStoredWorkAndIgnoreAUrl(): void {
        $rest   = new RestRegistrar();
        $module = new SeoModule();
        $module->registerRoutes( $rest );
        $routes = array_column( $rest->routes(), 'route' );

        self::assertContains( '/seo/checklist', $routes );
        self::assertContains( '/seo/templates', $routes );
        self::assertContains( '/seo/audit', $routes );

        $jobs = new JobRegistrar();
        $module->registerJobs( $jobs );
        self::assertTrue( $jobs->has( 'querynova.seo.audit' ) );
        $jobs->handle( 'querynova.seo.audit', [ 'url' => 'https://evil.test' ], [] );

        $stored = $GLOBALS['querynova_options'][ OnPageController::AUDIT_OPTION ];
        self::assertIsArray( $stored );
        $encoded = wp_json_encode( $stored );
        self::assertIsString( $encoded );
        self::assertStringNotContainsString( 'evil.test', $encoded );
        self::assertSame( 'info', $stored['findings'][0]['status'] );
        self::assertSame( 'no_stored_crawl', $stored['findings'][0]['code'] );
        self::assertSame( 'These findings do not estimate ranking impact.', $stored['note'] );
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, string>
     */
    private function check( array $report, string $id ): array {
        $checks = $report['checks'] ?? null;
        self::assertIsArray( $checks );
        foreach ( $checks as $row ) {
            self::assertIsArray( $row );
            if ( ( $row['id'] ?? '' ) === $id ) {
                return $row;
            }
        }
        self::fail( 'Missing check ' . $id );
    }
}
