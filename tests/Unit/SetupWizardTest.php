<?php
/**
 * The setup wizard stores answers and does not connect providers or crawl.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Infrastructure\WordPress\OptionStore;
use QueryNova\Modules\Core\CoreModule;
use QueryNova\Modules\Core\SetupWizard;

final class SetupWizardTest extends TestCase {

    public function testEmptySetupStaysEmpty(): void {
        $wizard = new SetupWizard( new OptionStore() );
        $setup  = $wizard->read( false );

        self::assertNull( $setup['site_type'] );
        self::assertNull( $setup['business_type'] );
        self::assertFalse( $setup['woocommerce'] );
        self::assertNull( $setup['organization']['name'] );
        self::assertSame( 'not_configured', $setup['search_console']['state'] );
        self::assertSame( 'not_configured', $setup['ga4']['state'] );
        self::assertNull( $setup['schema_enabled'] );
        self::assertNull( $setup['sitemap_enabled'] );
        self::assertNull( $setup['crawler_enabled'] );
        self::assertFalse( $setup['completed'] );
        self::assertFalse( $setup['connected'] );
        self::assertFalse( $setup['crawl_started'] );
    }

    public function testSavingAPropertyDoesNotConnectOrCrawl(): void {
        unset( $GLOBALS['querynova_options'][ SetupWizard::OPTION ] );
        $wizard  = new SetupWizard( new OptionStore() );
        $saved   = $wizard->save(
            [
                'site_type'       => 'store',
                'business_type'   => 'Brewery',
                'search_console'  => [
					'property' => 'sc-domain:example.test',
					'state'    => 'connected',
					'api_key'  => 'secret-value',
				],
                'ga4'             => 'G-TEST',
                'organization'    => [
					'name' => 'Valjevo',
					'url'  => 'javascript:alert(1)',
					'logo' => 'https://example.test/logo.png',
				],
                'crawler_origin'  => 'https://example.test/',
                'crawler_enabled' => true,
                'schema_enabled'  => true,
                'api_key'         => 'secret-value',
            ],
            true
        );
        $encoded = (string) wp_json_encode( $saved );

        self::assertSame( 'store', $saved['site_type'] );
        self::assertTrue( $saved['woocommerce'] );
        self::assertSame( 'not_configured', $saved['search_console']['state'] );
        self::assertSame( 'sc-domain:example.test', $saved['search_console']['property'] );
        self::assertSame( 'not_configured', $saved['ga4']['state'] );
        self::assertNull( $saved['organization']['url'] );
        self::assertSame( 'https://example.test/logo.png', $saved['organization']['logo'] );
        self::assertFalse( $saved['connected'] );
        self::assertFalse( $saved['crawl_started'] );
        self::assertTrue( $saved['completed'] );
        self::assertStringNotContainsString( 'secret-value', $encoded );
        self::assertStringNotContainsString( 'javascript:', $encoded );
    }

    public function testSetupRouteIsRegistered(): void {
        $rest = new RestRegistrar();
        ( new CoreModule() )->registerRoutes( $rest );
        $paths = array_map(
            static function ( array $route ): string {
                return $route['route'];
            },
            $rest->routes()
        );

        self::assertContains( '/setup', $paths );
    }
}
