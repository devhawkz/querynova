<?php
/**
 * Video markup uses supplied fields and does not fetch a page.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Schema\Application\VideoDocument;
use QueryNova\Modules\Schema\SchemaModule;

final class VideoDocumentTest extends TestCase {

    public function testASuppliedVideoBuildsSchemaAndSitemapWithoutFetching(): void {
        $built  = VideoDocument::build(
            [
                'title'         => 'Lager pour',
                'description'   => 'A pour',
                'content_url'   => 'https://example.test/lager.mp4',
                'thumbnail_url' => 'javascript:alert(1)',
            ]
        );
        $empty  = VideoDocument::build( [ 'title' => 'Lager pour' ] );
        $routes = new RestRegistrar();
        ( new SchemaModule() )->registerRoutes( $routes );
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Schema/Application/VideoDocument.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.

        self::assertSame( 'VideoObject', $built['schema']['@type'] );
        self::assertSame( 'https://example.test/lager.mp4', $built['sitemap']['content_url'] );
        self::assertNull( $built['sitemap']['thumbnail'] );
        self::assertFalse( $built['fetched'] );
        self::assertFalse( $built['saved'] );
        self::assertNull( $empty['schema'] );
        self::assertNull( $empty['sitemap'] );
        self::assertContains( '/schema/video', array_column( $routes->routes(), 'route' ) );
        self::assertIsString( $source );
        self::assertStringNotContainsString( 'wp_remote_', $source );
    }
}
