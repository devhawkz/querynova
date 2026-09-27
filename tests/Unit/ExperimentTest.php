<?php
/**
 * Experiment results describe movement and do not claim a cause.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Modules\Experiments\Application\ExperimentComparison;
use QueryNova\Modules\Experiments\ExperimentModule;

final class ExperimentTest extends TestCase {

    public function testMissingAfterIsNotZero(): void {
        $report = ( new ExperimentComparison() )->compare(
            'title_change',
            [
                'rank'    => 8,
                'clicks'  => 10,
                'revenue' => null,
            ],
            []
        );

        self::assertSame( 'UNAVAILABLE', $report['status'] );
        self::assertSame( 'inconclusive', $report['result'] );
        self::assertNull( $report['metrics']['rank']['delta'] );
        self::assertNull( $report['metrics']['clicks']['after'] );
        self::assertNull( $report['metrics']['revenue']['delta'] );
        self::assertFalse( $report['causation'] );
        self::assertStringContainsString( 'do not show that the change caused', $report['note'] );
    }

    public function testImprovedRankDoesNotClaimCausation(): void {
        $report = ( new ExperimentComparison() )->compare(
            'title_change',
            [
				'rank' => 12,
				'ctr'  => 0.02,
			],
            [
				'rank' => 4,
				'ctr'  => 0.05,
			]
        );

        self::assertSame( 'improved', $report['result'] );
        self::assertSame( 'MEASURED', $report['status'] );
        self::assertSame( -8.0, $report['metrics']['rank']['delta'] );
        self::assertSame( 'improved', $report['metrics']['rank']['direction'] );
        self::assertFalse( $report['causation'] );
        self::assertFalse( $report['applied'] );
    }

    public function testConflictingMetricsStayMixed(): void {
        $report = ( new ExperimentComparison() )->compare(
            'schema_change',
            [
                'rank'    => 6,
                'revenue' => 100,
            ],
            [
                'rank'    => 3,
                'revenue' => 40,
            ]
        );

        self::assertSame( 'mixed', $report['result'] );
        self::assertSame( 'declined', $report['metrics']['revenue']['direction'] );
        self::assertFalse( $report['causation'] );
    }

    public function testUnchangedMetricIsInconclusive(): void {
        $report = ( new ExperimentComparison() )->compare(
            'description_change',
            [ 'clicks' => 10 ],
            [ 'clicks' => 10 ]
        );

        self::assertSame( 'MEASURED', $report['status'] );
        self::assertSame( 0.0, $report['metrics']['clicks']['delta'] );
        self::assertNull( $report['metrics']['clicks']['direction'] );
        self::assertSame( 'inconclusive', $report['result'] );
    }

    public function testUnknownTypeIsRejected(): void {
        $this->expectException( ValidationException::class );
        ( new ExperimentComparison() )->compare( 'keyword_stuffing', [], [] );
    }

    public function testCloseStoresObservationWithoutCausation(): void {
        $module = new ExperimentModule();
        $opened = $module->open(
            [
                'name'        => 'Title test',
                'type'        => 'title_change',
                'object_type' => 'product',
                'object_id'   => 4,
                'before'      => [
                    'rank'   => 12,
                    'clicks' => 3,
                ],
            ]
        );
        $closed = $module->close(
            (int) $opened['id'],
            [
                'rank'   => 4,
                'clicks' => 12,
            ]
        );

        self::assertSame( 'running', $opened['status'] );
        self::assertFalse( $opened['causation'] );
        self::assertTrue( $closed['closed'] );
        self::assertSame( 'improved', $closed['result'] );
        self::assertFalse( $closed['causation'] );
        self::assertFalse( $module->close( (int) $opened['id'], [ 'rank' => 1 ] )['closed'] );
    }

    public function testModuleDoesNotApplyTheChange(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Experiments/ExperimentModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wp_remote_', $source );
        self::assertStringNotContainsString( 'template_redirect', $source );
    }

    public function testSafeModeOmitsExperiments(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'experiments', $names );
        self::assertNotContains(
            'experiments',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
