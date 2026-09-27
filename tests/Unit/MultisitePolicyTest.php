<?php
/**
 * Multisite activation must not fatal, and there is no network settings screen.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\MultisitePolicy;
use QueryNova\Core\Plugin;
use QueryNova\Infrastructure\WordPress\AdminPageRegistrar;

final class MultisitePolicyTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['qn_network_admin'], $GLOBALS['qn_menus'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores these keys.
        parent::tearDown();
    }

    public function testNetworkActivationStillPreparesOnlyTheCurrentSite(): void {
        $policy = new MultisitePolicy();
        $method = new \ReflectionMethod( Plugin::class, 'activate' );

        self::assertFalse( $policy->networkManagement() );
        self::assertTrue( $policy->activatesCurrentSite( true ) );
        self::assertTrue( $policy->activatesCurrentSite( false ) );
        self::assertSame( 'bool', (string) $method->getParameters()[0]->getType() );
        self::assertTrue( $method->getParameters()[0]->isOptional() );
    }

    public function testNetworkAdminDoesNotRegisterASiteMenu(): void {
        $GLOBALS['qn_network_admin'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores network admin state under this key.
        $GLOBALS['qn_menus']         = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap records registered menus under this key.
        $registrar                   = new AdminPageRegistrar();
        $registrar->add(
            'querynova',
            'QueryNova',
            'manage_options',
            static function (): void {
				// The menu callback is not invoked by this test.
			}
        );
        $registrar->register();
        $callbacks = $GLOBALS['qn_actions']['admin_menu'] ?? [];
        $callback  = $callbacks[ array_key_last( $callbacks ) ];
        self::assertIsCallable( $callback );
        $callback();

        self::assertSame( [], $GLOBALS['qn_menus'] );
        self::assertFalse( ( new MultisitePolicy() )->registersSiteMenu() );
    }

    public function testASiteAdminStillRegistersTheMenu(): void {
        $GLOBALS['qn_network_admin'] = false; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores network admin state under this key.
        $GLOBALS['qn_menus']         = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap records registered menus under this key.
        $registrar                   = new AdminPageRegistrar();
        $registrar->add(
            'querynova',
            'QueryNova',
            'manage_options',
            static function (): void {
				// The menu callback is not invoked by this test.
			}
        );
        $registrar->register();
        $callbacks = $GLOBALS['qn_actions']['admin_menu'] ?? [];
        $callback  = $callbacks[ array_key_last( $callbacks ) ];
        self::assertIsCallable( $callback );
        $callback();

        self::assertNotSame( [], $GLOBALS['qn_menus'] );
    }
}
