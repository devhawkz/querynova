<?php
/**
 * Analytics connection UX, screen model, and the local rank tracker.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Analytics\AnalyticsModule;
use QueryNova\Modules\Analytics\Application\AnalyticsScreens;
use QueryNova\Modules\Analytics\Application\ProviderConnections;
use QueryNova\Modules\Analytics\Domain\AnalyticsProvider;
use QueryNova\Modules\Analytics\Domain\AnalyticsRow;
use QueryNova\Modules\Analytics\Domain\SearchConsoleProvider;
use QueryNova\Modules\Analytics\Domain\SearchConsoleRow;
use QueryNova\Modules\Serp\Application\IndexAvailability;
use QueryNova\Modules\Serp\Application\RankTracker;
use QueryNova\Modules\Serp\SerpModule;

final class AnalyticsScreensTest extends TestCase {

    protected function setUp(): void {
        $this->forgetOptions();
    }

    protected function tearDown(): void {
        $this->forgetOptions();
        parent::tearDown();
    }

    public function testDisconnectedProvidersStayNotConnected(): void {
        $card = ProviderConnections::connect( 'search_console', 'sc-domain:example.test', '' );

        self::assertSame( 'Not connected', $card['state'] );
        self::assertNull( $card['clicks'] );
        self::assertNull( $card['impressions'] );
        self::assertNull( $card['sessions'] );
        self::assertNull( $card['revenue'] );
        self::assertFalse( $GLOBALS['querynova_options'][ ProviderConnections::OPTION ]['search_console']['connected'] );

        $GLOBALS['querynova_options'][ ProviderConnections::OPTION ]['search_console']['connected'] = true;
        $stale = ProviderConnections::card( 'search_console', '' );
        self::assertSame( 'Not connected', $stale['state'] );
        self::assertNull( $stale['clicks'] );

        $cleared = ProviderConnections::disconnect( 'search_console' );
        self::assertSame( '', $cleared['property'] );
        self::assertSame( 'Not connected', $cleared['state'] );
    }

    public function testConnectionProbeUsesProviderRowsOnly(): void {
        $silent = new class() implements SearchConsoleProvider {
            public int $calls = 0;

            public function id(): string {
                return '';
            }

            public function rows( string $property, string $start, string $end ): ?array {
                unset( $property, $start, $end );
                ++$this->calls;

                return null;
            }
        };
        $missed = ProviderConnections::probeSearch( $silent, 'sc-domain:example.test', '2026-01-01', '2026-01-07' );
        self::assertSame( 0, $silent->calls );
        self::assertSame( 'Not connected', $missed['state'] );
        self::assertNull( $missed['clicks'] );
        self::assertNull( $missed['revenue'] );

        $live  = new class() implements SearchConsoleProvider {
            public int $calls = 0;

            public function id(): string {
                return 'fixture';
            }

            public function rows( string $property, string $start, string $end ): ?array {
                unset( $property, $start, $end );
                ++$this->calls;

                return [ new SearchConsoleRow( '2026-01-02', 1, 1, 'lager', 'rs', 'desktop', 4, 40, null, 2.0 ) ];
            }
        };
        $found = ProviderConnections::probeSearch( $live, 'sc-domain:example.test', '2026-01-01', '2026-01-07' );
        self::assertSame( 1, $live->calls );
        self::assertSame( 4, $found['clicks'] );
        self::assertSame( 40, $found['impressions'] );
        self::assertNull( $found['sessions'] );
        self::assertNull( $found['revenue'] );

        $ga4       = new class() implements AnalyticsProvider {
            public int $calls = 0;

            public function id(): string {
                return '';
            }

            public function rows( string $property, string $start, string $end ): ?array {
                unset( $property, $start, $end );
                ++$this->calls;

                return null;
            }
        };
        $analytics = ProviderConnections::probeAnalytics( $ga4, 'properties/1', '2026-01-01', '2026-01-07' );
        self::assertSame( 0, $ga4->calls );
        self::assertSame( 'Not connected', $analytics['state'] );

        $measured = ProviderConnections::analyticsTest(
            'fixture',
            [ new AnalyticsRow( '2026-01-02', 1, 12, 10, 8, null, null, null, null, null, 'google', 'organic' ) ]
        );
        self::assertSame( 12, $measured['sessions'] );
        self::assertNull( $measured['revenue'] );
        self::assertNull( $measured['clicks'] );
        self::assertNull( $measured['impressions'] );
    }

    public function testResyncQueuesOnlyAConnectedProvider(): void {
        $blocked = ProviderConnections::resyncPlan( 'ga4', '', '2026-01-01', '2026-01-07' );
        self::assertFalse( $blocked['queued'] );
        self::assertSame( 'Not connected', $blocked['state'] );
        self::assertArrayNotHasKey( 'payload', $blocked );

        ProviderConnections::connect( 'ga4', 'properties/1', 'fixture' );
        $plan = ProviderConnections::resyncPlan( 'ga4', 'fixture', '2026-01-01', '2026-01-07' );
        self::assertTrue( $plan['queued'] );
        self::assertSame(
            [
                'property' => 'properties/1',
                'start'    => '2026-01-01',
                'end'      => '2026-01-07',
            ],
            $plan['payload']
        );
        self::assertArrayNotHasKey( 'url', $plan['payload'] );
        $encoded = wp_json_encode( $plan );
        self::assertIsString( $encoded );
        self::assertStringNotContainsString( 'fetch', $encoded );
        self::assertStringNotContainsString( 'http', $encoded );
        self::assertNull( ProviderConnections::card( 'ga4', 'fixture' )['sessions'] );
        self::assertNull( ProviderConnections::card( 'ga4', 'fixture' )['revenue'] );
    }

    public function testScreensComparePeriodsWithoutInventingMetrics(): void {
        $range = AnalyticsScreens::periods( '2026-01-08', '2026-01-14' );
        self::assertSame( '2026-01-01', $range['previous_start'] );
        self::assertSame( '2026-01-07', $range['previous_end'] );

        $search = ProviderConnections::card( 'search_console', '' );
        $ga4    = ProviderConnections::card( 'ga4', '' );
        $empty  = AnalyticsScreens::present( $search, $ga4, '2026-01-08', '2026-01-14', null, null, null, null, null, [], [] );
        self::assertSame(
            [
                'overview',
                'seo_performance',
                'keywords',
                'content',
                'rank_tracker',
                'index_status',
                'traffic',
                'commerce',
                'ai',
            ],
            array_column( $empty['screens'], 'id' )
        );
        self::assertSame( 'Not connected', $empty['kpis'][0]['state'] );
        self::assertNull( $empty['kpis'][0]['value'] );
        self::assertNull( $empty['kpis'][2]['value'] );
        self::assertNull( $empty['kpis'][3]['value'] );
        self::assertNull( $empty['comparison']['winning_keywords'] );
        self::assertNull( $empty['comparison']['losing_posts'] );
        self::assertSame( 'Not available', $empty['index_status']['state'] );
        self::assertSame( 'Not available', $empty['trends']['state'] );
        self::assertNull( $empty['experience']['lcp'] );
        self::assertNull( $empty['experience']['inp'] );
        self::assertNull( $empty['experience']['cls'] );
        self::assertNull( $empty['experience']['ttfb'] );
        self::assertNull( $empty['commerce']['revenue'] );
        self::assertSame( 'Not available', $empty['ai']['state'] );
        self::assertArrayNotHasKey( 'chart', $empty );
        self::assertArrayNotHasKey( 'series', $empty );

        $current  = [
            new SearchConsoleRow( '2026-01-10', 1, 1, 'lager', 'rs', 'desktop', 10, 100, null, 2.0 ),
            new SearchConsoleRow( '2026-01-10', 2, 2, 'pilsner', 'rs', 'desktop', 1, 20, null, 8.0 ),
        ];
        $previous = [
            new SearchConsoleRow( '2026-01-03', 1, 1, 'lager', 'rs', 'desktop', 4, 80, null, 5.0 ),
            new SearchConsoleRow( '2026-01-03', 2, 2, 'pilsner', 'rs', 'desktop', 6, 30, null, 3.0 ),
        ];
        $filled   = AnalyticsScreens::present( $search, $ga4, '2026-01-08', '2026-01-14', $current, $previous, null, null, null, [], [] );
        self::assertSame( [ 'lager' ], $filled['comparison']['winning_keywords'] );
        self::assertSame( [ 'pilsner' ], $filled['comparison']['losing_keywords'] );
        self::assertSame( 1, $filled['comparison']['winning_posts'][0]['page_id'] );
        self::assertSame( 2, $filled['comparison']['losing_posts'][0]['page_id'] );
        self::assertSame( 'Not connected', $filled['kpis'][2]['state'] );
    }

    public function testIndexStatusAndTrendsNeedASupplier(): void {
        $withheld = IndexAvailability::report( '', [ [ 'url' => 'https://example.test' ] ], 'Index status' );
        self::assertSame( 'Not available', $withheld['state'] );
        self::assertNull( $withheld['rows'] );

        $supplied = IndexAvailability::report( 'fixture', [ [ 'url' => 'https://example.test' ] ], 'Trends' );
        self::assertSame( 'Supplied', $supplied['state'] );
        self::assertSame( 'https://example.test', $supplied['rows'][0]['url'] );
        self::assertStringContainsString( 'No chart is drawn', $supplied['note'] );
    }

    public function testRankTrackerStoresKeywordsWithoutAVendor(): void {
        $invalid = RankTracker::add(
            [
				'keyword' => 'lager',
				'device'  => 'watch',
			]
        );
        self::assertSame( 'invalid', $invalid['status'] );
        self::assertSame( [], RankTracker::keywords() );

        $bulk = RankTracker::addMany(
            [
                [
                    'keyword'  => 'lager',
                    'group'    => 'beer',
                    'location' => 'Valjevo',
                    'language' => 'sr',
                    'device'   => 'desktop',
                    'country'  => 'rs',
                ],
                [
                    'keyword' => '',
                    'device'  => 'desktop',
                ],
            ]
        );
        self::assertSame( 1, $bulk['added'] );
        self::assertSame( 1, $bulk['skipped'] );
        self::assertSame( 'Valjevo', RankTracker::keywords()[0]['location'] );
        self::assertSame( 'sr', RankTracker::keywords()[0]['language'] );
        self::assertSame( 'desktop', RankTracker::keywords()[0]['device'] );

        $csv = RankTracker::importCsv( "keyword,group,location,language,device,country\npilsner,beer,Belgrade,en,mobile,rs\n" );
        self::assertSame( 1, $csv['added'] );
        self::assertSame( 'pilsner', RankTracker::keywords()[1]['keyword'] );
        self::assertSame( 'mobile', RankTracker::keywords()[1]['device'] );
        self::assertStringContainsString( 'No SERP vendor', $csv['note'] );

        $history = RankTracker::history(
            [
				[
					'keyword_id' => 1,
					'position'   => null,
					'url'        => '',
					'device'     => 'mobile',
					'language'   => 'sr',
					'country'    => 'rs',
				],
			]
        );
        self::assertNull( $history['rows'][0]['position'] );
        self::assertStringContainsString( 'not zero', RankTracker::history( [] )['note'] );
        self::assertSame( 'Not available', RankTracker::catalog()['index_status']['state'] );
        self::assertSame( 'Not available', RankTracker::catalog()['trends']['state'] );
    }

    public function testRoutesAndScreenModelDoNotInventMetrics(): void {
        $model = AnalyticsModule::screenModel( '', '', '2026-01-08', '2026-01-14' );
        self::assertSame( 'Not connected', $model['search_console']['state'] );
        self::assertSame( 'Not connected', $model['ga4']['state'] );
        self::assertNull( $model['kpis'][0]['value'] );
        self::assertNull( $model['kpis'][3]['value'] );

        $analytics = new RestRegistrar();
        ( new AnalyticsModule() )->registerRoutes( $analytics );
        $analyticsRoutes = array_column( $analytics->routes(), 'route' );
        self::assertContains( '/analytics/connections', $analyticsRoutes );
        self::assertContains( '/analytics/screens', $analyticsRoutes );

        $serp = new RestRegistrar();
        ( new SerpModule() )->registerRoutes( $serp );
        $serpRoutes = array_column( $serp->routes(), 'route' );
        self::assertContains( '/serp/keywords', $serpRoutes );
        self::assertContains( '/serp/keywords/bulk', $serpRoutes );
        self::assertContains( '/serp/keywords/csv', $serpRoutes );
        self::assertContains( '/serp/index-status', $serpRoutes );

        $source = '';
        foreach ( [ 'src/Modules/Analytics/Application/ProviderConnections.php', 'src/Modules/Analytics/Application/AnalyticsScreens.php', 'src/Modules/Serp/Application/RankTracker.php', 'src/Modules/Serp/Application/IndexAvailability.php', 'assets/admin/features/analytics/AnalyticsScreen.tsx', 'assets/admin/features/rank/RankScreen.tsx' ] as $file ) {
            $source .= (string) file_get_contents( QUERYNOVA_PATH . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        }
        $lower = strtolower( $source );
        self::assertStringNotContainsString( 'wp_remote_', $lower );
        self::assertStringNotContainsString( 'googleapis.com', $lower );
        self::assertStringNotContainsString( 'dataforseo', $lower );
        self::assertStringNotContainsString( 'serpapi', $lower );
        self::assertStringNotContainsString( 'chart.js', $lower );
    }

    private function forgetOptions(): void {
        unset(
            $GLOBALS['querynova_options'][ ProviderConnections::OPTION ],
            $GLOBALS['querynova_options'][ RankTracker::OPTION ]
        );
    }
}
