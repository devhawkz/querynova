<?php
/**
 * The schema screen is opened with settings access. That user can load rules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Schema\SchemaModule;

final class SchemaRulesAccessTest extends TestCase {

    public function testTheScreenCapabilityCanLoadAndSaveRules(): void {
        $rest = new RestRegistrar();
        ( new SchemaModule() )->registerRoutes( $rest );
        $routes = [];
        foreach ( $rest->routes() as $route ) {
            if ( $route['route'] === '/schema/rules' ) {
                $routes[ $route['method'] ] = $route;
            }
        }

        self::assertArrayHasKey( 'GET', $routes );
        self::assertArrayHasKey( 'PUT', $routes );
        $GLOBALS['qn_caps'] = [ Capability::MANAGE_SETTINGS ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        self::assertTrue( $rest->allowedAny( $routes['GET']['capabilities'] ) );
        self::assertTrue( $rest->allowedAny( $routes['PUT']['capabilities'] ) );

        $GLOBALS['qn_caps'] = [ Capability::MANAGE_SEO ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        self::assertTrue( $rest->allowedAny( $routes['GET']['capabilities'] ) );

        $GLOBALS['qn_caps'] = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        self::assertFalse( $rest->allowedAny( $routes['GET']['capabilities'] ) );
        $saved = $GLOBALS['querynova_options']['querynova_schema_rules'] ?? null;
        $GLOBALS['querynova_options']['querynova_schema_rules'] = 'not-a-list';
        self::assertSame( [], SchemaModule::storedRules() );
        if ( $saved === null ) {
            unset( $GLOBALS['querynova_options']['querynova_schema_rules'] );
        } else {
            $GLOBALS['querynova_options']['querynova_schema_rules'] = $saved;
        }
        unset( $GLOBALS['qn_caps'] );
    }
}
