<?php
/**
 * Reports leave missing metrics empty and do not invent a PDF.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Modules\Ai\Application\AiVisibility;
use QueryNova\Modules\Reports\ReportModule;

final class ReportTest extends TestCase {

    public function testMissingRevenueStaysNull(): void {
        $report  = ( new ReportModule() )->render( 'executive', [ 'organic_orders' => 4 ], 'json' );
        $decoded = json_decode( (string) $report['body'], true );

        self::assertIsArray( $decoded );
        self::assertNull( $decoded['values']['organic_revenue'] );
        self::assertNull( $decoded['values']['organic_growth'] );
        self::assertSame( 4, $decoded['values']['organic_orders'] );
        self::assertSame( AiVisibility::DISCLAIMER, $decoded['disclaimer'] );
    }

    public function testCsvUsesAnEmptyCellInsteadOfZero(): void {
        $report = ( new ReportModule() )->render( 'executive', [], 'csv' );

        self::assertStringContainsString( 'organic_revenue', (string) $report['body'] );
        self::assertStringNotContainsString( 'organic_revenue,0', (string) $report['body'] );
        self::assertMatchesRegularExpression( '/organic_revenue,organic_orders.*\n,/', (string) $report['body'] );
    }

    public function testGrowthUsesBothPeriods(): void {
        $report  = ( new ReportModule() )->render(
            'executive',
            [
                'previous_revenue' => 80,
                'organic_revenue'  => 100,
            ],
            'json'
        );
        $decoded = json_decode( (string) $report['body'], true );

        self::assertSame( 20, $decoded['values']['organic_growth'] );
    }

    public function testPdfIsNotGenerated(): void {
        $report = ( new ReportModule() )->render( 'seo', [ 'clicks' => 3 ], 'pdf' );

        self::assertNull( $report['body'] );
        self::assertSame( 'PDF is not generated.', $report['note'] );
    }

    public function testUnknownReportIsRejected(): void {
        $this->expectException( ValidationException::class );
        ( new ReportModule() )->render( 'rankings', [], 'json' );
    }

    public function testSafeModeOmitsReports(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'reports', $names );
        self::assertNotContains(
            'reports',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
