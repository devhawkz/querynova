<?php
/**
 * Page experience timings stay null when they were not measured, and they are not an SEO score.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\Schema;
use QueryNova\Modules\Experience\Application\ExperienceSummary;
use QueryNova\Modules\Experience\Domain\ExperienceReport;
use QueryNova\Modules\Experience\ExperienceModule;
use QueryNova\Modules\Experience\Infrastructure\ExperienceRepository;
use QueryNova\Modules\Experience\Infrastructure\NullPageExperienceProvider;

final class ExperienceTest extends TestCase {

    public function testMissingReportIsNotZero(): void {
        $summary = ( new ExperienceSummary() )->summarize( null, 'mobile' );

        self::assertSame( 'UNAVAILABLE', $summary['status'] );
        self::assertNull( $summary['lcp']['value'] );
        self::assertNull( $summary['inp']['value'] );
        self::assertNull( $summary['cls']['value'] );
        self::assertNull( $summary['ttfb']['value'] );
        self::assertNull( $summary['seo_score'] );
        self::assertStringContainsString( 'not an SEO score', $summary['note'] );
        self::assertNull( ( new NullPageExperienceProvider() )->report( 'https://shop.example/', 'mobile' ) );
    }

    public function testOmittedTimingStaysNull(): void {
        $summary = ( new ExperienceSummary() )->summarize( new ExperienceReport( 2.4, null, 0.05, null ), 'desktop' );

        self::assertSame( 'MEASURED', $summary['status'] );
        self::assertSame( 2.4, $summary['lcp']['value'] );
        self::assertSame( 'UNAVAILABLE', $summary['inp']['provenance'] );
        self::assertNull( $summary['ttfb']['value'] );
        self::assertNull( $summary['seo_score'] );
    }

    public function testStoredRowKeepsNullTimings(): void {
        $database = new ArrayDatabase();
        $store    = new ExperienceRepository( $database );
        $store->save( 'https://shop.example/welder', 'mobile', 'fixture', new ExperienceReport( 1.8, null, null, 0.4 ) );
        $row = $store->latest( 'https://shop.example/welder', 'mobile' );

        self::assertSame( 1.8, $row['lcp'] );
        self::assertNull( $row['inp'] );
        self::assertNull( $row['cls'] );
        self::assertSame( 0.4, $row['ttfb'] );
        self::assertArrayNotHasKey( 'performance_score', $row );
    }

    public function testNullProviderStoresNothing(): void {
        $report = ( new ExperienceModule() )->collect( 'https://shop.example/', 'desktop' );

        self::assertSame( 'UNAVAILABLE', $report['status'] );
        self::assertNull( $report['seo_score'] );
    }

    public function testSchemaIncludesPageExperience(): void {
        $sql = ( new Schema() )->statementFor( 'wp_', 'ENGINE=InnoDB', 'page_experience' );

        self::assertStringContainsString( 'CREATE TABLE wp_qn_page_experience (', $sql );
        self::assertStringContainsString( 'lcp decimal(10,3) NULL', $sql );
        self::assertStringNotContainsString( 'seo_score', $sql );
    }

    public function testModuleDoesNotCallPageSpeed(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Experience/ExperienceModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        $nulls  = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Experience/Infrastructure/NullPageExperienceProvider.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wp_remote_', $source . $nulls );
        self::assertStringNotContainsString( 'pagespeedonline', $source . $nulls );
    }

    public function testSafeModeOmitsExperience(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'experience', $names );
        self::assertNotContains(
            'experience',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
