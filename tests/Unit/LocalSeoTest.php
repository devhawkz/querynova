<?php
/**
 * Local SEO loads only when enabled and does not rewrite URLs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Local\LocalGate;
use QueryNova\Modules\Local\LocalModule;
use QueryNova\Modules\Seo\SeoModule;

final class LocalSeoTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ LocalGate::OPTION ] );
        unset( $GLOBALS['querynova_options'][ LocalGate::LOCATIONS ] );
    }

    public function testTheModuleStaysOutUntilTheOptionIsExactlyTrue(): void {
        self::assertNotContains( 'local', $this->names( false ) );
        self::assertNotContains( 'local', $this->names( true ) );
        $GLOBALS['querynova_options'][ LocalGate::OPTION ] = 'true';
        self::assertFalse( LocalGate::enabled() );
        self::assertNotContains( 'local', $this->names( false ) );
        $GLOBALS['querynova_options'][ LocalGate::OPTION ] = true;
        self::assertContains( 'local', $this->names( false ) );
        self::assertNotContains( 'local', $this->names( true ) );

        $off = new RestRegistrar();
        unset( $GLOBALS['querynova_options'][ LocalGate::OPTION ] );
        ( new LocalModule() )->registerRoutes( $off );
        $held   = LocalGate::save( true, false );
        $stored = LocalGate::saveLocation( 'Valjevo', '', true );
        self::assertNull( LocalGate::present()['locations'] );

        $GLOBALS['querynova_options'][ LocalGate::OPTION ] = true;
        $on = new RestRegistrar();
        ( new LocalModule() )->registerRoutes( $on );
        $seo = new RestRegistrar();
        ( new SeoModule() )->registerRoutes( $seo );
        $capability = '';
        foreach ( $seo->routes() as $route ) {
            if ( $route['route'] === '/local' ) {
                $capability = $route['capability'];
            }
        }
        $gate   = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Local/LocalGate.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        $module = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Local/LocalModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.

        self::assertNotContains( '/local/status', array_column( $off->routes(), 'route' ) );
        self::assertContains( '/local/status', array_column( $on->routes(), 'route' ) );
        self::assertContains( '/local', array_column( $seo->routes(), 'route' ) );
        self::assertContains( '/local/locations', array_column( $seo->routes(), 'route' ) );
        self::assertSame( Capability::MANAGE_SEO, $capability );
        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['url_changed'] );
        self::assertFalse( $held['flushed'] );
        self::assertFalse( $stored['stored'] );
        self::assertNull( $stored['locations'] );
        self::assertFalse( $stored['url_changed'] );
        self::assertFalse( $stored['flushed'] );
        self::assertIsString( $gate );
        self::assertIsString( $module );
        self::assertStringNotContainsString( 'flush_rewrite_rules', $gate . $module );
        self::assertStringNotContainsString( 'register_post_type', $gate . $module );
    }

    public function testAConfirmedLocationIsStoredWithoutChangingUrls(): void {
        $GLOBALS['querynova_options'][ LocalGate::OPTION ] = true;
        $saved = LocalGate::saveLocation( 'Taproom', '', true );

        self::assertTrue( $saved['stored'] );
        self::assertFalse( $saved['url_changed'] );
        self::assertFalse( $saved['flushed'] );
        self::assertSame( 'Taproom', $saved['locations'][0]['name'] );
        self::assertNull( $saved['locations'][0]['address'] );
    }

    /**
     * @return list<string>
     */
    private function names( bool $safeMode ): array {
        $names = [];
        foreach ( ModuleCatalog::modules( $safeMode ) as $module ) {
            $names[] = $module->getName();
        }

        return $names;
    }
}
