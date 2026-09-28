<?php
/**
 * Editor panel tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Seo\Application\EditorAdapters;
use QueryNova\Modules\Seo\Application\EditorDocument;
use QueryNova\Modules\Seo\Application\EditorSave;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;
use QueryNova\Modules\Seo\Presentation\EditorPanel;
use QueryNova\Modules\Seo\Presentation\ElementorEditorAdapter;
use QueryNova\Modules\Seo\SeoModule;

final class EditorPanelTest extends TestCase {

    public function testViewReadsStoredFieldsAndLeavesThemUnchanged(): void {
        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        $service->save(
            'post',
            8,
            [
				SeoMetaService::TITLE        => 'Stored title',
				SeoMetaService::ROBOTS_INDEX => 'noindex',
			]
        );
        $before = $service->stored( 'post', 8, SeoMetaService::TITLE );
        $view   = EditorDocument::view( $service, 'post', 8, 'Post title', 'https://example.test/guide' );

        self::assertSame( 'Stored title', $view['stored']['title'] );
        self::assertSame( 'Stored title', $view['preview']['title'] );
        self::assertSame( 'https://example.test/guide', $view['preview']['url'] );
        self::assertTrue( $view['dangerous']['robots_index'] );
        self::assertFalse( $view['dangerous']['canonical'] );
        self::assertSame( $before, $service->stored( 'post', 8, SeoMetaService::TITLE ) );
        self::assertSame( '', $service->stored( 'post', 9, SeoMetaService::TITLE ) );
    }

    public function testEmptyTitlePreviewUsesTheDocumentTitle(): void {
        $service = new SeoMetaService( new ArrayMetaStore(), new TemplateRenderer() );
        $view    = EditorDocument::view( $service, 'post', 3, 'Post title', 'https://example.test/post' );

        self::assertSame( '', $view['stored']['title'] );
        self::assertSame( 'Post title', $view['preview']['title'] );
    }

    public function testElementorStaysUnmountedAndTheTabsAreFixed(): void {
        $elementor = new ElementorEditorAdapter();
        $described = EditorAdapters::describe();
        $ids       = array_column( $described, 'id' );

        self::assertSame( [ 'General', 'Advanced', 'Schema', 'Social', 'AI/Content', 'Links' ], EditorAdapters::tabs() );
        self::assertSame( [ 'gutenberg', 'classic', 'woocommerce', 'elementor' ], $ids );
        self::assertFalse( $elementor->isMounted() );
        self::assertFalse( $elementor->isActive() );
        self::assertTrue( EditorAdapters::supports( 'post', true, true ) );
        self::assertTrue( EditorAdapters::supports( 'page', true, true ) );
        self::assertTrue( EditorAdapters::supports( 'product', true, false ) );
        self::assertTrue( EditorAdapters::supports( 'guide', true, true ) );
        self::assertFalse( EditorAdapters::supports( 'guide', true, false ) );
        self::assertFalse( EditorAdapters::supports( 'guide', false, true ) );
        self::assertFalse( EditorAdapters::supports( 'attachment', true, true ) );
        self::assertFalse( EditorAdapters::supports( 'revision', true, true ) );
    }

    public function testFormSaveWritesOneDocumentAndSkipsAutosave(): void {
        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        $panel   = new EditorPanel( $service );
        $request = [
            'querynova_editor_fields' => '1',
            'querynova_title'         => 'One document',
            'querynova_focus_keyword' => 'lager, pilsner',
            'object_ids'              => '1,2,3',
        ];

        self::assertFalse( $panel->apply( 4, $request, true, false, true, true, true ) );
        self::assertFalse( $panel->apply( 4, [ 'querynova_title' => 'Skipped' ], false, false, true, true, true ) );
        self::assertTrue( $panel->apply( 4, $request, false, false, true, true, true ) );
        self::assertSame( 'One document', $service->stored( 'post', 4, SeoMetaService::TITLE ) );
        self::assertSame( 'lager, pilsner', $service->stored( 'post', 4, SeoMetaService::FOCUS_KEYWORD ) );
        self::assertSame( '', $service->stored( 'post', 5, SeoMetaService::TITLE ) );
        self::assertSame(
            [
                'title'         => 'One document',
                'focus_keyword' => 'lager, pilsner',
            ],
            EditorSave::fields( $request )
        );
    }

    public function testInvalidCanonicalDoesNotSaveTheDocument(): void {
        $service = new SeoMetaService( new ArrayMetaStore(), new TemplateRenderer() );
        $panel   = new EditorPanel( $service );
        try {
            $panel->apply(
                6,
                [
                    'querynova_editor_fields' => '1',
                    'querynova_title'         => 'Should stay empty',
                    'querynova_canonical'     => 'javascript:alert(1)',
                ],
                false,
                false,
                true,
                true,
                true
            );
            self::fail( 'Expected the canonical to be rejected.' );
        } catch ( ValidationException $exception ) {
            self::assertSame( 'Canonical and image values must be http or https URLs.', $exception->getMessage() );
        }
        self::assertSame( '', $service->stored( 'post', 6, SeoMetaService::TITLE ) );
    }

    public function testEditorRouteIsRegistered(): void {
        $rest = new RestRegistrar();
        ( new SeoModule() )->registerRoutes( $rest );
        $routes = array_column( $rest->routes(), 'route' );

        self::assertContains( '/seo/editor', $routes );
        self::assertContains( '/seo/meta', $routes );
    }
}
