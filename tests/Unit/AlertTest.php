<?php
/**
 * Missing metrics do not become alerts.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Modules\Alerts\AlertModule;
use QueryNova\Modules\Alerts\Application\AlertEvaluator;

final class AlertTest extends TestCase {

    public function testMissingNumbersDoNotAlert(): void {
        $alerts = ( new AlertEvaluator() )->evaluate(
            [
                'previous_rank'    => 4,
                'current_rank'     => null,
                'previous_revenue' => null,
                'current_revenue'  => 0,
                'schema_failure'   => false,
            ]
        );

        self::assertSame( [], $alerts );
    }

    public function testRankDropAndRevenueLossUseSuppliedValues(): void {
        $alerts = ( new AlertEvaluator() )->evaluate(
            [
                'previous_rank'    => 4,
                'current_rank'     => 20,
                'previous_revenue' => 100,
                'current_revenue'  => 40,
                'object'           => 'product:4',
            ]
        );
        $types  = array_column( $alerts, 'type' );

        self::assertContains( 'major_rank_loss', $types );
        self::assertContains( 'revenue_loss', $types );
        self::assertNotContains( 'traffic_anomaly', $types );
    }

    public function testSmallRankMoveIsNotMajor(): void {
        $alerts = ( new AlertEvaluator() )->evaluate(
            [
                'previous_rank' => 4,
                'current_rank'  => 6,
            ]
        );

        self::assertSame( [], $alerts );
    }

    public function testDuplicateEvidenceDoesNotOpenASecondAlert(): void {
        $module = new AlertModule();
        $input  = [
            'object'           => 'product:4',
            'indexability'     => 'noindex',
            'previous_traffic' => 100,
            'current_traffic'  => 10,
        ];
        $first  = $module->evaluate( $input );
        $second = $module->evaluate( $input );

        self::assertSame( 2, $first['stored'] );
        self::assertSame( 0, $second['stored'] );
        self::assertCount( 2, $second['alerts'] );
    }

    public function testModuleDoesNotFetch(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Alerts/AlertModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wp_remote_', $source );
    }

    public function testSafeModeOmitsAlerts(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'alerts', $names );
        self::assertNotContains(
            'alerts',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
