<?php
/**
 * The product screen shows stored fields and does not fill missing metrics.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Core\ProductScreen;

final class ProductScreenTest extends TestCase {

    public function testNoProductStaysEmpty(): void {
        $screen = ( new ProductScreen() )->fromDatabase( new ArrayDatabase() );

        self::assertNull( $screen['title'] );
        foreach ( array_keys( ProductScreen::emptyTabs() ) as $tab ) {
            self::assertSame( [], $screen['tabs'][ $tab ], $tab );
        }
    }

    public function testMissingNumbersStayOffTheTabsAndEstimatesStayEstimated(): void {
        $database = new ArrayDatabase();
        $database->insert(
            'wp_qn_keywords',
            [
                'keyword' => 'lager',
            ]
        );
        $database->insert(
            'wp_qn_products',
            [
                'product_id'         => 8,
                'sku'                => 'LAGER-1',
                'brand'              => 'Valjevo',
                'price'              => '19.90',
                'currency'           => 'EUR',
                'availability'       => 'instock',
                'indexability'       => 'unknown',
                'primary_keyword_id' => 1,
            ]
        );
        $database->insert(
            'wp_qn_product_metrics',
            [
                'product_id'      => 8,
                'impressions'     => null,
                'clicks'          => 3,
                'position'        => null,
                'cvr'             => null,
                'revenue'         => '4.50',
                'ai_referrals'    => 2,
                'provenance_json' => wp_json_encode(
                    [
                        'clicks'  => 'MEASURED',
                        'revenue' => 'ESTIMATED',
                    ]
                ),
            ]
        );
        $database->insert(
            'wp_qn_revenue_metrics',
            [
                'scope'              => 'product',
                'scope_id'           => 8,
                'measured_revenue'   => null,
                'attributed_revenue' => null,
                'estimated_revenue'  => '4.50',
            ]
        );

        $screen  = ( new ProductScreen() )->fromDatabase( $database );
        $encoded = (string) wp_json_encode( $screen );
        $revenue = array_map(
            static function ( array $item ): string {
                return (string) $item['summary'];
            },
            $screen['tabs']['revenue']
        );

        self::assertSame( 'LAGER-1', $screen['title'] );
        self::assertContains( '19.90 EUR · Measured', array_column( $screen['tabs']['overview'], 'summary' ) );
        self::assertSame( 'lager', $screen['tabs']['keywords'][0]['title'] );
        self::assertSame( '3 · Measured', $screen['tabs']['search'][0]['summary'] );
        self::assertCount( 1, $screen['tabs']['search'] );
        self::assertContains( '4.50 · Estimated', $revenue );
        self::assertNotContains( '4.50 · Measured', $revenue );
        self::assertSame( [], $screen['tabs']['conversion'] );
        self::assertSame( [], $screen['tabs']['content'] );
        self::assertSame( [], $screen['tabs']['schema'] );
        self::assertSame( [], $screen['tabs']['links'] );
        self::assertSame( [], $screen['tabs']['competitors'] );
        self::assertSame( [], $screen['tabs']['recommendations'] );
        self::assertStringContainsString( 'Not an official provider ranking.', $screen['tabs']['ai'][0]['summary'] );
        self::assertStringNotContainsString( 'Indexability', $encoded );
        self::assertStringNotContainsString( 'Impressions', $encoded );
    }
}
