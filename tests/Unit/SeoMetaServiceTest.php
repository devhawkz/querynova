<?php
/**
 * SEO template and robots tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\RobotsDirective;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;

final class SeoMetaServiceTest extends TestCase {

    public function testTemplateFillsMissingTitleAndDropsUnknownTokens(): void {
        $service  = new SeoMetaService( new ArrayMetaStore(), new TemplateRenderer() );
        $document = $service->resolve(
            'post',
            10,
            [
                'title'    => 'Buying guide',
                'sep'      => '-',
                'sitename' => 'Northwind',
                'excerpt'  => 'How to choose a welder.',
            ],
            []
        );

        self::assertSame( 'Buying guide - Northwind', $document->title() );
        self::assertSame( 'How to choose a welder.', $document->description() );
        self::assertSame( 'index, follow', $document->robots()->content() );
    }

    public function testExplicitNoindexIsNotOverwrittenByTheTemplate(): void {
        $store = new ArrayMetaStore();
        $store->set( 'post', 10, SeoMetaService::TITLE, 'Custom title' );
        $store->set( 'post', 10, SeoMetaService::ROBOTS_INDEX, 'noindex' );
        $service  = new SeoMetaService( $store, new TemplateRenderer() );
        $document = $service->resolve(
            'post',
            10,
            [
				'title'    => 'Other',
				'sep'      => '-',
				'sitename' => 'Site',
				'excerpt'  => '',
			],
			[]
        );

        self::assertSame( 'Custom title', $document->title() );
        self::assertSame( 'noindex, follow', $document->robots()->content() );
        self::assertFalse( $document->robots()->index() );
    }

    public function testSaveRejectsANonHttpCanonical(): void {
        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        try {
            $service->save(
                'post',
                4,
                [
                    SeoMetaService::TITLE     => 'Keep me off the store',
                    SeoMetaService::CANONICAL => 'javascript:alert(1)',
                ]
            );
            self::fail( 'Expected the canonical to be rejected.' );
        } catch ( \QueryNova\Core\Exceptions\ValidationException $exception ) {
            self::assertSame( 'Canonical and image values must be http or https URLs.', $exception->getMessage() );
        }
        self::assertSame( '', $service->stored( 'post', 4, SeoMetaService::TITLE ) );
        self::assertSame( '', $service->stored( 'post', 4, SeoMetaService::CANONICAL ) );
    }

    public function testRobotsStringsRoundTrip(): void {
        self::assertSame( 'noindex, nofollow', RobotsDirective::fromStrings( 'noindex', 'nofollow' )->content() );
    }
}
