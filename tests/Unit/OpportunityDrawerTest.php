<?php
/**
 * The opportunity drawer records a status and leaves the live page alone.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Opportunities\Application\OpportunityDrawer;
use QueryNova\Modules\Opportunities\OutcomeModule;

final class OpportunityDrawerTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ OpportunityDrawer::OPTION ] );
    }

    public function testDetailLeavesMissingFieldsEmpty(): void {
        $detail = OpportunityDrawer::present(
            [
                'title'      => 'Rewrite the title',
                'rationale'  => 'Clicks fell',
                'confidence' => 'Medium',
            ]
        );

        self::assertSame( 'Clicks fell', $detail['why'] );
        self::assertNull( $detail['evidence'] );
        self::assertNull( $detail['kpi'] );
        self::assertNull( $detail['risks'] );
        self::assertNull( $detail['url'] );
        self::assertSame( [], $detail['sources'] );
        self::assertFalse( $detail['page_changed'] );
    }

    public function testActionsDoNotChangeTheLivePage(): void {
        $held   = OpportunityDrawer::act( 4, 'experiment', false );
        $stored = OpportunityDrawer::act( 4, 'experiment', true );
        $routes = new RestRegistrar();
        ( new OutcomeModule() )->registerRoutes( $routes );
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Opportunities/Application/OpportunityDrawer.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.

        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['page_changed'] );
        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['page_changed'] );
        self::assertFalse( $stored['published'] );
        self::assertSame( 'experiment', get_option( OpportunityDrawer::OPTION, [] )['action'] );
        self::assertContains( '/outcomes/drawer', array_column( $routes->routes(), 'route' ) );
        self::assertIsString( $source );
        self::assertStringNotContainsString( 'wp_update_post', $source );
    }
}
