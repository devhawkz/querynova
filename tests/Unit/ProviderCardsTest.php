<?php
/**
 * Provider cards stay Not connected and do not call a vendor.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Core\CoreModule;
use QueryNova\Modules\Core\ProviderCards;

final class ProviderCardsTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ ProviderCards::OPTION ] );
    }

    public function testAnEmptyProviderStaysNotConnectedAndConfigureDoesNotCallOut(): void {
        $card   = ProviderCards::present( 'Search Console', 'not_configured' );
        $held   = ProviderCards::configure( 'Search Console', false );
        $stored = ProviderCards::configure( 'Search Console', true );
        $routes = new RestRegistrar();
        ( new CoreModule() )->registerRoutes( $routes );
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/ProviderCards.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.

        self::assertSame( 'Not connected', $card['state'] );
        self::assertFalse( $card['called'] );
        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['called'] );
        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['called'] );
        self::assertStringContainsString( 'No vendor was called', $stored['note'] );
        self::assertContains( '/providers/configure', array_column( $routes->routes(), 'route' ) );
        self::assertIsString( $source );
        self::assertStringNotContainsString( 'wp_remote_', $source );
    }
}
