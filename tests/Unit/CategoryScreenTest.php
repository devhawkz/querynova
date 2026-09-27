<?php
/**
 * The category screen leaves a default product count empty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Core\CategoryScreen;

final class CategoryScreenTest extends TestCase {

    public function testNoCategoryStaysEmpty(): void {
        $screen = ( new CategoryScreen() )->fromDatabase( new ArrayDatabase() );

        self::assertNull( $screen['title'] );
        foreach ( array_keys( CategoryScreen::emptyTabs() ) as $tab ) {
            self::assertSame( [], $screen['tabs'][ $tab ], $tab );
        }
    }

    public function testZeroProductCountAndMissingRevenueStayEmpty(): void {
        $database = new ArrayDatabase();
        $database->insert( 'wp_qn_keywords', [ 'keyword' => 'pivo' ] );
        $database->insert(
            'wp_qn_categories',
            [
                'term_id'            => 15,
                'product_count'      => 0,
                'primary_keyword_id' => 1,
            ]
        );
        $database->insert(
            'wp_qn_category_metrics',
            [
                'category_id'     => 15,
                'impressions'     => null,
                'clicks'          => 2,
                'position'        => null,
                'revenue'         => '9.00',
                'opportunity'     => 88,
                'provenance_json' => wp_json_encode(
                    [
						'revenue' => 'ESTIMATED',
						'clicks'  => 'MEASURED',
					]
                ),
            ]
        );
        $database->insert(
            'wp_qn_revenue_metrics',
            [
                'scope'              => 'category',
                'scope_id'           => 15,
                'measured_revenue'   => null,
                'attributed_revenue' => null,
                'estimated_revenue'  => '4.50',
            ]
        );

        $screen  = ( new CategoryScreen() )->fromDatabase( $database );
        $encoded = (string) wp_json_encode( $screen );
        $revenue = array_map(
            static function ( array $item ): string {
                return (string) $item['summary'];
            },
            $screen['tabs']['revenue']
        );

        self::assertSame( 'Category 15', $screen['title'] );
        self::assertSame( [], $screen['tabs']['products'] );
        self::assertSame( [], $screen['tabs']['filters'] );
        self::assertSame( [], $screen['tabs']['content'] );
        self::assertSame( [], $screen['tabs']['ai'] );
        self::assertSame( 'pivo', $screen['tabs']['keywords'][0]['title'] );
        self::assertSame( '2 · Measured', $screen['tabs']['serp'][0]['summary'] );
        self::assertContains( '4.50 · Estimated', $revenue );
        self::assertContains( '9.00 · Estimated', $revenue );
        self::assertNotContains( '9.00 · Measured', $revenue );
        self::assertStringNotContainsString( '88', $encoded );
        self::assertStringNotContainsString( 'Impressions', $encoded );
    }
}
