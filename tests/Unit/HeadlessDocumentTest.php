<?php
/**
 * Headless SEO uses the existing capability and leaves schema empty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Seo\Application\HeadlessDocument;
use QueryNova\Modules\Seo\SeoModule;

final class HeadlessDocumentTest extends TestCase {

    public function testTheContractKeepsMissingSchemaEmptyAndUsesTheSeoCapability(): void {
        $document = HeadlessDocument::present(
            [
                'title'       => 'Lager',
                'description' => '',
                'canonical'   => 'https://example.test/lager',
                'robots'      => 'index, follow',
            ]
        );
        $routes   = new RestRegistrar();
        ( new SeoModule() )->registerRoutes( $routes );
        $capability = '';
        foreach ( $routes->routes() as $route ) {
            if ( $route['route'] === '/headless/seo' ) {
                $capability = $route['capability'];
            }
        }

        self::assertSame( 'Lager', $document['metadata']['title'] );
        self::assertNull( $document['metadata']['description'] );
        self::assertSame( 'https://example.test/lager', $document['canonical'] );
        self::assertSame( 'index, follow', $document['robots'] );
        self::assertNull( $document['schema'] );
        self::assertNull( $document['social']['twitter_card'] );
        self::assertFalse( $document['fetched'] );
        self::assertSame( Capability::MANAGE_SEO, $capability );
    }
}
