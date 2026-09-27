<?php
/**
 * SEO import copies post meta and does not disable the other plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Redirects\Application\RedirectEngine;
use QueryNova\Modules\Redirects\Infrastructure\RedirectRepository;
use QueryNova\Modules\Seo\Application\SeoImporter;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;
use QueryNova\Modules\Seo\Infrastructure\WordPressForeignMetaReader;
use QueryNova\Modules\Seo\SeoModule;

final class SeoImportTest extends TestCase {

    public function testYoastMetaIsStoredAndTheOtherPluginStaysEnabled(): void {
        $store    = new ArrayMetaStore();
        $importer = $this->importer( $store );
        $result   = $importer->importMeta(
            'yoast',
            [
                [
                    'object_type' => 'post',
                    'object_id'   => 12,
                    'meta'        => [
                        '_yoast_wpseo_title'               => 'Brewery guide',
                        '_yoast_wpseo_metadesc'            => 'How the beer is made.',
                        '_yoast_wpseo_canonical'           => 'https://example.test/guide',
                        '_yoast_wpseo_meta-robots-noindex' => '1',
                        '_yoast_wpseo_meta-robots-nofollow' => '1',
                        '_yoast_wpseo_focuskw'             => 'lager, pilsner, extra, fourth, fifth, sixth',
                    ],
                ],
            ],
            false
        );
        $service  = new SeoMetaService( $store, new TemplateRenderer() );

        self::assertSame( 6, $result['written'] );
        self::assertFalse( $result['disabled_other_plugin'] );
        self::assertSame( 'Brewery guide', $service->stored( 'post', 12, SeoMetaService::TITLE ) );
        self::assertSame( 'noindex', $service->stored( 'post', 12, SeoMetaService::ROBOTS_INDEX ) );
        self::assertSame( 'nofollow', $service->stored( 'post', 12, SeoMetaService::ROBOTS_FOLLOW ) );
        self::assertSame( 'lager, pilsner, extra, fourth, fifth', $service->stored( 'post', 12, SeoMetaService::FOCUS_KEYWORD ) );
        self::assertStringNotContainsString( 'sixth', $service->stored( 'post', 12, SeoMetaService::FOCUS_KEYWORD ) );
    }

    public function testExistingTitleIsKeptUnlessReplaceIsRequested(): void {
        $store = new ArrayMetaStore();
        $store->set( 'post', 4, SeoMetaService::TITLE, 'Already written' );
        $importer = $this->importer( $store );
        $object   = [
            [
                'object_type' => 'post',
                'object_id'   => 4,
                'meta'        => [ '_yoast_wpseo_title' => 'Imported title' ],
            ],
        ];

        $kept = $importer->importMeta( 'yoast', $object, false );
        self::assertSame( 0, $kept['written'] );
        self::assertSame( 1, $kept['skipped'] );
        self::assertSame( 'Already written', ( new SeoMetaService( $store, new TemplateRenderer() ) )->stored( 'post', 4, SeoMetaService::TITLE ) );

        $importer->importMeta( 'yoast', $object, true );
        self::assertSame( 'Imported title', ( new SeoMetaService( $store, new TemplateRenderer() ) )->stored( 'post', 4, SeoMetaService::TITLE ) );
    }

    public function testForeignTemplatesAndBadCanonicalsAreNotCopied(): void {
        $store    = new ArrayMetaStore();
        $importer = $this->importer( $store );
        $result   = $importer->importMeta(
            'rankmath',
            [
                [
                    'object_type' => 'post',
                    'object_id'   => 8,
                    'meta'        => [
                        'rank_math_title'         => '%%title%% %sep% %sitename%',
                        'rank_math_description'   => 'A plain description',
                        'rank_math_canonical_url' => 'javascript:alert(1)',
                        'rank_math_robots'        => 'a:2:{i:0;s:7:"noindex";}',
                        'rank_math_focus_keyword' => '#post_title',
                    ],
                ],
            ],
            false
        );
        $service  = new SeoMetaService( $store, new TemplateRenderer() );

        self::assertSame( 1, $result['written'] );
        self::assertSame( 1, $result['rejected'] );
        self::assertSame( '', $service->stored( 'post', 8, SeoMetaService::TITLE ) );
        self::assertSame( 'A plain description', $service->stored( 'post', 8, SeoMetaService::DESCRIPTION ) );
        self::assertSame( '', $service->stored( 'post', 8, SeoMetaService::ROBOTS_INDEX ) );
        self::assertSame( '', $service->stored( 'post', 8, SeoMetaService::FOCUS_KEYWORD ) );
    }

    public function testRankMathAndAioseoRobotsMapWithoutUnserialize(): void {
        $store    = new ArrayMetaStore();
        $importer = $this->importer( $store );
        $importer->importMeta(
            'rankmath',
            [
                [
                    'object_type' => 'post',
                    'object_id'   => 3,
                    'meta'        => [ 'rank_math_robots' => [ 'noindex', 'follow' ] ],
                ],
            ],
            false
        );
        $importer->importMeta(
            'aioseo',
            [
                [
                    'object_type' => 'term',
                    'object_id'   => 9,
                    'meta'        => [
                        '_aioseo_title'          => 'Category',
                        '_aioseo_robots_noindex' => 'on',
                        '_aioseo_keywords'       => 'ale',
                    ],
                ],
            ],
            false
        );
        $service = new SeoMetaService( $store, new TemplateRenderer() );

        self::assertSame( 'noindex', $service->stored( 'post', 3, SeoMetaService::ROBOTS_INDEX ) );
        self::assertSame( 'follow', $service->stored( 'post', 3, SeoMetaService::ROBOTS_FOLLOW ) );
        self::assertSame( 'Category', $service->stored( 'term', 9, SeoMetaService::TITLE ) );
        self::assertSame( 'noindex', $service->stored( 'term', 9, SeoMetaService::ROBOTS_INDEX ) );
        self::assertSame( 'ale', $service->stored( 'term', 9, SeoMetaService::FOCUS_KEYWORD ) );
    }

    public function testUnknownPluginIsRejected(): void {
        $importer = $this->importer( new ArrayMetaStore() );
        $this->expectException( ValidationException::class );
        $importer->importMeta( 'other', [], false );
    }

    public function testRedirectsImportOnlyValidRowsAndDoNotDisableThePlugin(): void {
        $repository = new RedirectRepository( new ArrayDatabase() );
        $importer   = new SeoImporter(
            new SeoMetaService( new ArrayMetaStore(), new TemplateRenderer() ),
            new RedirectEngine(),
            $repository
        );
        $result     = $importer->importRedirects(
            [
                [
					'source' => '/old',
					'target' => '/new',
					'status' => 301,
				],
                [
					'source' => '/new',
					'target' => '/old',
					'status' => 301,
				],
                [
					'source' => '/missing-status',
					'target' => '/new',
				],
                [
					'source' => '/regex',
					'target' => '/new',
					'status' => 301,
					'regex'  => true,
				],
                [
					'source' => '/bad',
					'target' => 'javascript:alert(1)',
					'status' => 301,
				],
            ]
        );

        self::assertSame( 1, $result['imported'] );
        self::assertSame( 4, $result['rejected'] );
        self::assertFalse( $result['disabled_other_plugin'] );
        self::assertCount( 1, $repository->all() );
    }

    public function testMetaReaderStaysEmptyWithoutWordPressMeta(): void {
        if ( function_exists( 'get_post_meta' ) ) {
            self::markTestSkipped( 'WordPress meta functions are loaded.' );
        }

        self::assertSame( [], ( new WordPressForeignMetaReader() )->read( 'post', 1, 'yoast' ) );
    }

    public function testImportRouteIsRegistered(): void {
        $rest = new RestRegistrar();
        ( new SeoModule() )->registerRoutes( $rest );
        $paths = array_map(
            static function ( array $route ): string {
                return $route['route'];
            },
            $rest->routes()
        );

        self::assertContains( '/seo/import', $paths );
    }

    private function importer( ArrayMetaStore $store ): SeoImporter {
        return new SeoImporter(
            new SeoMetaService( $store, new TemplateRenderer() ),
            new RedirectEngine(),
            new RedirectRepository( new ArrayDatabase() )
        );
    }
}
