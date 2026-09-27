<?php
/**
 * A recommendation is improved or declined only after measured before and after values.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;
use QueryNova\Modules\Opportunities\OutcomeModule;

final class OutcomeTest extends TestCase {

    private ?RecommendationRepository $recommendations = null;

    public function testSuggestedRecommendationIsNotApplied(): void {
        $module = $this->module();
        $id     = $this->seed();

        $applied = $module->markApplied( $id, [ 'title' => 'Old' ], [ 'title' => 'New' ] );

        self::assertFalse( $applied['applied'] );
        self::assertFalse( $applied['changed_page'] );
    }

    public function testMeasureWithoutPerformanceStaysUnmeasured(): void {
        $module = $this->module();
        $id     = $this->seed();
        $module->accept( $id );
        $module->markApplied( $id, null, null );
        $measured = $module->measure( $id, [ 'rank' => 8 ], [] );

        self::assertFalse( $measured['measured'] );
        self::assertNull( $measured['outcome'] );
        self::assertFalse( $measured['causation'] );
        self::assertSame( 'applied', $this->recommendations()->find( $id )['status'] );
    }

    public function testMeasuredImprovementDoesNotClaimCausation(): void {
        $module = $this->module();
        $id     = $this->seed();
        $module->accept( $id );
        $module->markApplied( $id, [ 'title' => 'Old' ], [ 'title' => 'New' ] );
        $measured = $module->measure(
            $id,
            [
				'rank'    => 11,
				'revenue' => 20,
			],
            [
				'rank'    => 4,
				'revenue' => 20,
			]
        );
        $row      = $this->recommendations()->find( $id );

        self::assertTrue( $measured['measured'] );
        self::assertSame( 'improved', $measured['outcome'] );
        self::assertFalse( $measured['causation'] );
        self::assertSame( 'measured', $row['status'] );
        self::assertSame( 'improved', $row['outcome'] );
    }

    public function testConflictingMovementIsInconclusive(): void {
        $module = $this->module();
        $id     = $this->seed();
        $module->accept( $id );
        $module->markApplied( $id, null, null );
        $measured = $module->measure(
            $id,
            [
                'rank'    => 9,
                'revenue' => 80,
            ],
            [
                'rank'    => 3,
                'revenue' => 10,
            ]
        );

        self::assertSame( 'inconclusive', $measured['outcome'] );
        self::assertFalse( $measured['causation'] );
    }

    public function testModuleDoesNotChangeThePage(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Opportunities/OutcomeModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'update_post_meta', $source );
        self::assertStringNotContainsString( 'wp_update_post', $source );
        self::assertStringNotContainsString( 'wp_remote_', $source );
    }

    public function testSafeModeOmitsOutcomes(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'outcomes', $names );
        self::assertNotContains(
            'outcomes',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }

    private function module(): OutcomeModule {
        $this->recommendations = new RecommendationRepository( new ArrayDatabase() );

        return new OutcomeModule( $this->recommendations );
    }

    private function seed(): int {
        $repository = $this->recommendations();
        $repository->save(
            [
                [
                    'title'        => 'Test the title and snippet',
                    'description'  => 'Review the supplied title.',
                    'url'          => 'https://shop.example/welder',
                    'target_query' => 'welder',
                    'impact'       => 'unavailable',
                    'confidence'   => 'LOW',
                    'effort'       => 'low',
                    'evidence'     => [],
                    'data_sources' => [],
                    'expected_kpi' => 'ctr',
                    'rationale'    => 'Business value was high.',
                    'priority'     => 'high',
                ],
            ]
        );
        $rows = $repository->today();

        return (int) $rows[0]['id'];
    }

    private function recommendations(): RecommendationRepository {
        $store = $this->recommendations;
        self::assertInstanceOf( RecommendationRepository::class, $store );

        return $store;
    }
}
