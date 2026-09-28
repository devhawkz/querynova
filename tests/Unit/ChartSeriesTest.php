<?php
/**
 * Sparklines keep a missing metric empty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Core\ChartSeries;

final class ChartSeriesTest extends TestCase {

    public function testMissingPointsStayEmptyAndZeroStaysZero(): void {
        $chart = ChartSeries::present(
            [
                [ 'position' => 4 ],
                [ 'position' => null ],
                [ 'position' => '2' ],
            ],
            [
                [
                    'clicks'      => null,
                    'impressions' => 8,
                ],
                [
                    'clicks'      => 0,
                    'impressions' => 3,
                ],
            ],
            [
                [
                    'measured_revenue'   => null,
                    'attributed_revenue' => 12.5,
                    'estimated_revenue'  => 0,
                ],
            ],
            [
                [ 'revenue' => 99 ],
            ]
        );

        self::assertSame(
            [
                [
                    'value' => 4.0,
                    'kind'  => 'MEASURED',
                ],
                [
                    'value' => null,
                    'kind'  => 'UNAVAILABLE',
                ],
                [
                    'value' => 2.0,
                    'kind'  => 'MEASURED',
                ],
            ],
            $chart['series'][0]['points']
        );
        self::assertNull( $chart['series'][1]['points'][0]['value'] );
        self::assertSame( 'UNAVAILABLE', $chart['series'][1]['points'][0]['kind'] );
        self::assertSame( 0.0, $chart['series'][1]['points'][1]['value'] );
        self::assertSame( 8.0, $chart['series'][2]['points'][0]['value'] );
        self::assertSame( 'ATTRIBUTED', $chart['series'][4]['points'][0]['kind'] );
        self::assertSame( 0.0, $chart['series'][5]['points'][0]['value'] );
        self::assertSame( 'ESTIMATED', $chart['series'][5]['points'][0]['kind'] );
        self::assertNull( $chart['series'][3]['points'][0]['value'] );
        self::assertFalse( $chart['fetched'] );
        self::assertFalse( $chart['written'] );
        self::assertFalse( $chart['called'] );
    }

    public function testCommerceRevenueIsUsedOnlyWhenTheProvenanceRowsAreMissing(): void {
        $fallback = ChartSeries::present( [], [], [], [ [ 'revenue' => 9 ] ] );

        self::assertSame( [], $fallback['series'][0]['points'] );
        self::assertSame( 9.0, $fallback['series'][3]['points'][0]['value'] );
        self::assertSame( 'MEASURED', $fallback['series'][3]['points'][0]['kind'] );
        self::assertSame( [], $fallback['series'][4]['points'] );
        self::assertSame( [], $fallback['series'][5]['points'] );
    }

    public function testDatabaseReadsKeepTheNewestTwentyInOrder(): void {
        $database = new ArrayDatabase( 'wp_' );
        for ( $index = 1; $index <= 21; $index++ ) {
            $database->insert(
                'wp_qn_rank_history',
                [
                    'id'       => $index,
                    'position' => $index === 21 ? null : $index,
                ]
            );
        }
        $database->insert(
            'wp_qn_gsc_metrics',
            [
                'id'          => 1,
                'clicks'      => 2,
                'impressions' => null,
            ]
        );

        $chart = ChartSeries::fromDatabase( $database );
        $rank  = $chart['series'][0]['points'];

        self::assertCount( ChartSeries::LIMIT, $rank );
        self::assertSame( 2.0, $rank[0]['value'] );
        self::assertNull( $rank[19]['value'] );
        self::assertSame( 'UNAVAILABLE', $rank[19]['kind'] );
        self::assertSame( 2.0, $chart['series'][1]['points'][0]['value'] );
        self::assertNull( $chart['series'][2]['points'][0]['value'] );
    }

    public function testTheSeriesReaderDoesNotWriteOrCallOut(): void {
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/ChartSeries.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        self::assertIsString( $source );
        self::assertStringNotContainsString( 'update_option(', $source );
        self::assertStringNotContainsString( 'update_post_meta(', $source );
        self::assertStringNotContainsString( 'wp_remote_', $source );
        self::assertStringNotContainsString( 'wp-config.php', $source );
    }
}
