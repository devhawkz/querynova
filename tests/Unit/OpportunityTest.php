<?php
/**
 * Opportunities stay unavailable when the inputs are missing, and nothing is applied.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Opportunities\Application\OpportunityEngine;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;
use QueryNova\Modules\Opportunities\OpportunityModule;

final class OpportunityTest extends TestCase {

    public function testMissingInputsAreUnavailable(): void {
        $report = ( new OpportunityEngine() )->evaluate( [] );

        self::assertSame( 'UNAVAILABLE', $report['status'] );
        self::assertNull( $report['opportunity'] );
        self::assertNull( $report['incremental_revenue']['value'] );
        self::assertNull( $report['priority'] );
        self::assertSame( [], $report['recommendations'] );
        self::assertArrayNotHasKey( 'score', $report );
    }

    public function testOutOfStockDoesNotRecommendGrowth(): void {
        $report = ( new OpportunityEngine() )->evaluate(
            [
                'inventory'   => 'out_of_stock',
                'impressions' => 40000,
                'revenue'     => 20000,
                'position'    => 2,
            ]
        );

        self::assertNull( $report['opportunity'] );
        self::assertSame( [], $report['recommendations'] );
        self::assertFalse( $report['applied'] );
        self::assertStringContainsString( 'out of stock', $report['note'] );
    }

    public function testCtrGapEstimatesRevenueAndDoesNotApply(): void {
        $report = ( new OpportunityEngine() )->evaluate(
            [
                'impressions'          => 1000,
                'ctr'                  => 0.02,
                'expected_ctr'         => 0.04,
                'clicks'               => 20,
                'revenue'              => 200.0,
                'margin'               => 0.5,
                'impressions_are_high' => true,
                'position_is_good'     => true,
                'ctr_is_weak'          => true,
                'business_value'       => 'critical',
                'query'                => 'welder',
                'url'                  => 'https://shop.example/welder',
                'effort'               => 'low',
            ]
        );

        self::assertSame( 'title_snippet', $report['opportunity'] );
        self::assertSame( 'ESTIMATED', $report['incremental_revenue']['provenance'] );
        self::assertSame( 200.0, $report['incremental_revenue']['value'] );
        self::assertSame( 100.0, $report['incremental_profit']['value'] );
        self::assertSame( 'LOW', $report['confidence'] );
        self::assertSame( 'high', $report['priority'] );
        self::assertFalse( $report['recommendations'][0]['applied'] );
        self::assertSame( 'ctr', $report['recommendations'][0]['expected_kpi'] );
    }

    public function testMissingRevenueDoesNotBecomeZero(): void {
        $report = ( new OpportunityEngine() )->evaluate(
            [
                'position'    => 14,
                'impressions' => 800,
                'query'       => 'welder',
            ]
        );

        self::assertSame( 'ranking', $report['opportunity'] );
        self::assertNull( $report['incremental_revenue']['value'] );
        self::assertSame( 'UNAVAILABLE', $report['incremental_revenue']['provenance'] );
        self::assertNull( $report['priority'] );
        self::assertSame( [], $report['recommendations'] );
    }

    public function testMarginOutsideRatioStaysUnavailable(): void {
        $report = ( new OpportunityEngine() )->evaluate(
            [
                'impressions'          => 1000,
                'ctr'                  => 0.02,
                'expected_ctr'         => 0.04,
                'clicks'               => 20,
                'revenue'              => 200.0,
                'margin'               => 40,
                'impressions_are_high' => true,
                'position_is_good'     => true,
                'ctr_is_weak'          => true,
                'business_value'       => 'high',
            ]
        );

        self::assertSame( 200.0, $report['incremental_revenue']['value'] );
        self::assertNull( $report['incremental_profit']['value'] );
    }

    public function testDecayAndCtrNeedEvidence(): void {
        $engine  = new OpportunityEngine();
        $decay   = $engine->decay(
            [
                'current_clicks'       => 4,
                'previous_clicks'      => 10,
                'current_impressions'  => 40,
                'previous_impressions' => 100,
            ]
        );
        $unknown = $engine->decay( [] );
        $ctr     = $engine->ctr( [ 'impressions_are_high' => true ] );

        self::assertSame( [ 'demand_decline' ], $decay['classification'] );
        self::assertNull( $unknown['classification'] );
        self::assertNull( $ctr['opportunity'] );
        self::assertSame( 'UNAVAILABLE', $ctr['status'] );
    }

    public function testSuggestionsAreIdempotentAndNotApplied(): void {
        $database = new ArrayDatabase();
        $store    = new RecommendationRepository( $database );
        $row      = [
            'title'        => 'Test the title and snippet',
            'description'  => 'Review it.',
            'url'          => 'https://shop.example/welder',
            'target_query' => 'welder',
            'impact'       => 'estimated',
            'confidence'   => 'LOW',
            'effort'       => 'low',
            'evidence'     => [ 'ctr' ],
            'data_sources' => [ 'search' ],
            'expected_kpi' => 'ctr',
            'rationale'    => 'Business value was critical.',
            'priority'     => 'high',
        ];
        $store->save( [ $row ] );
        $store->save( [ $row ] );
        $saved = $store->today();

        self::assertCount( 1, $saved );
        self::assertSame( 'suggested', $saved[0]['status'] );
        self::assertSame( '', $saved[0]['outcome'] );
    }

    public function testModuleDoesNotApplyChanges(): void {
        $report = ( new OpportunityModule() )->inspect(
            [
                'position'       => 11,
                'impressions'    => 100,
                'business_value' => 'high',
                'query'          => 'welder',
                'url'            => 'https://shop.example/welder',
            ]
        );
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Opportunities/OpportunityModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertFalse( $report['applied'] );
        self::assertSame( 1, $report['stored'] );
        self::assertStringNotContainsString( 'wp_remote_', $source );
        self::assertStringNotContainsString( 'template_redirect', $source );
    }

    public function testSafeModeOmitsOpportunities(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'opportunities', $names );
        self::assertNotContains(
            'opportunities',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
