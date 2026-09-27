<?php
/**
 * Analytics stay unavailable until a provider returns rows.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Analytics\AnalyticsModule;
use QueryNova\Modules\Analytics\Application\AnalyticsWorkspace;
use QueryNova\Modules\Analytics\Domain\AnalyticsRow;
use QueryNova\Modules\Analytics\Domain\CommerceMetricRow;
use QueryNova\Modules\Analytics\Domain\SearchConsoleRow;
use QueryNova\Modules\Analytics\Infrastructure\AnalyticsRepository;
use QueryNova\Modules\Analytics\Infrastructure\NullSearchConsoleProvider;

final class AnalyticsTest extends TestCase {

    public function testDisconnectedSearchIsNotZero(): void {
        $summary = ( new AnalyticsWorkspace() )->search( null );

        self::assertSame( 'UNAVAILABLE', $summary['status'] );
        self::assertNull( $summary['clicks'] );
        self::assertNull( $summary['ctr'] );
        self::assertNull( ( new NullSearchConsoleProvider() )->rows( 'sc-domain:example.com', '2026-01-01', '2026-01-31' ) );
    }

    public function testEmptyProviderListIsMeasuredZeroWithoutCtr(): void {
        $summary = ( new AnalyticsWorkspace() )->search( [] );

        self::assertSame( 'MEASURED', $summary['status'] );
        self::assertSame( 0, $summary['clicks'] );
        self::assertNull( $summary['ctr'] );
        self::assertNull( $summary['position'] );
    }

    public function testPartialRowDoesNotBecomeZero(): void {
        $summary = ( new AnalyticsWorkspace() )->search(
            [
                new SearchConsoleRow( '2026-01-01', 1, 1, 'welder', '', 'desktop', 10, null, null, 4.0 ),
            ]
        );

        self::assertSame( 'UNAVAILABLE', $summary['status'] );
        self::assertNull( $summary['clicks'] );
    }

    public function testPositionStaysNullWhenOneRowOmitsIt(): void {
        $summary = ( new AnalyticsWorkspace() )->search(
            [
                new SearchConsoleRow( '2026-01-01', 1, 1, 'welder', '', 'desktop', 10, 100, null, 2.0 ),
                new SearchConsoleRow( '2026-01-01', 1, 2, 'mig', '', 'desktop', 5, 50, null, null ),
            ]
        );

        self::assertSame( 15, $summary['clicks'] );
        self::assertSame( 0.1, $summary['ctr'] );
        self::assertNull( $summary['position'] );
    }

    public function testMovementNeedsBothPeriods(): void {
        $workspace = new AnalyticsWorkspace();
        $current   = [ new SearchConsoleRow( '2026-02-01', 1, 1, 'welder', '', 'desktop', 8, 40, null, 3.0 ) ];
        $missing   = $workspace->movement( $current, null );
        $previous  = [ new SearchConsoleRow( '2026-01-01', 1, 1, 'welder', '', 'desktop', 2, 40, null, 8.0 ) ];
        $moved     = $workspace->movement( $current, $previous );

        self::assertNull( $missing['growing_queries'] );
        self::assertSame( [ 'welder' ], $moved['growing_queries'] );
        self::assertSame( [ 'welder' ], $moved['ranking_gains'] );
        self::assertSame( [], $moved['ranking_losses'] );
    }

    public function testOverviewAndQueryRevenueDoNotInventJoins(): void {
        $workspace = new AnalyticsWorkspace();
        $cards     = $workspace->overview( null, 20, null, 2, null, null, null, null );
        $hidden    = $workspace->queryRevenue( 99.0, false, 'last_click' );
        $joined    = $workspace->queryRevenue( 99.0, true, 'querynova.order_query_join' );

        self::assertNull( $cards['organic_clicks']['value'] );
        self::assertSame( 'UNAVAILABLE', $cards['organic_revenue']['provenance'] );
        self::assertSame( 'MEASURED', $cards['organic_cvr']['provenance'] );
        self::assertSame( 0.1, $cards['organic_cvr']['value'] );
        self::assertNull( $cards['revenue_per_session']['value'] );
        self::assertNull( $hidden['value'] );
        self::assertSame( 'ATTRIBUTED', $joined['provenance'] );
        self::assertSame( 'MEDIUM', $joined['confidence'] );
    }

    public function testProfitInventorySeasonalityAndFunnel(): void {
        $workspace = new AnalyticsWorkspace();
        $profit    = $workspace->profit( 100.0, null );
        $stock     = $workspace->inventory( 'out_of_stock' );
        $year      = $workspace->seasonality( 10.0, null );
        $funnel    = $workspace->funnel(
            [
                'landing'      => 100,
                'product_view' => null,
                'add_to_cart'  => 10,
                'checkout'     => 4,
                'purchase'     => 2,
            ]
        );

        self::assertNull( $profit['profit'] );
        self::assertFalse( $stock['push_traffic'] );
        self::assertFalse( $stock['applied'] );
        self::assertNull( $year['delta'] );
        self::assertNull( $funnel['dropoff']['product_view'] );
        self::assertSame( 0.5, $funnel['dropoff']['purchase'] );
        self::assertNull( $workspace->product( [ 'clicks' => 3 ] )['revenue'] );
        self::assertNull( $workspace->product( [ 'clicks' => 3 ] )['opportunity'] );
    }

    public function testDiagnosisSeparatesConversionFromVisibility(): void {
        $workspace  = new AnalyticsWorkspace();
        $conversion = $workspace->diagnose(
            [
                'conversion_weak' => true,
                'visibility_weak' => false,
                'stock'           => 'in_stock',
            ]
        );
        $unknown    = $workspace->diagnose( [] );

        self::assertSame( 'conversion', $conversion['problem'] );
        self::assertNull( $workspace->ctrIsWeak( 0.02, null ) );
        self::assertTrue( $workspace->ctrIsWeak( 0.01, 0.04 ) );
        self::assertNull( $unknown['problem'] );
        self::assertSame( 'UNAVAILABLE', $unknown['status'] );
    }

    public function testRepositorySkipsIncompleteRowsAndNullProviderStoresNothing(): void {
        $database = new ArrayDatabase();
        $store    = new AnalyticsRepository( $database );
        $saved    = $store->saveSearch(
            'sc-domain:example.com',
            [
                new SearchConsoleRow( '2026-01-01', 1, 1, 'welder', 'rs', 'desktop', 4, 40, null, 2.5 ),
                new SearchConsoleRow( '2026-01-01', 1, 2, 'mig', 'rs', 'desktop', null, 10, null, 1.0 ),
            ]
        );
        $store->saveTraffic(
            [
                new AnalyticsRow( '2026-01-01', 1, 10, 8, 6, null, null, null, null, null, 'google', 'organic' ),
            ]
        );
        $store->saveCommerce(
            [
                new CommerceMetricRow( '2026-01-01', 5, 2, 20, 4, 2, 1, null, 0.0, 1.0 ),
            ]
        );
        $module = ( new AnalyticsModule() )->collect( 'sc-domain:example.com', '2026-01-01', '2026-01-31' );

        self::assertSame( 1, $saved );
        self::assertSame( 0.1, $database->select( 'wp_qn_gsc_metrics', [], 1 )[0]['ctr'] );
        self::assertSame( [], $database->select( 'wp_qn_ga_metrics', [], 5 ) );
        self::assertSame( [], $database->select( 'wp_qn_commerce_metrics', [], 5 ) );
        self::assertSame( 0, $module['stored_search_rows'] );
        self::assertFalse( $module['fetched_on_frontend'] );
        self::assertSame( 'UNAVAILABLE', $module['search']['status'] );
    }

    public function testModuleDoesNotCallProvidersInline(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Analytics/AnalyticsModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        $nulls  = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Analytics/Infrastructure/NullSearchConsoleProvider.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wp_remote_', $source . $nulls );
        self::assertStringNotContainsString( 'googleapis.com', $source . $nulls );
        self::assertStringNotContainsString( 'template_redirect', $source );
    }

    public function testSafeModeOmitsAnalytics(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'analytics', $names );
        self::assertNotContains(
            'analytics',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
