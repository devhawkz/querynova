<?php
/**
 * Commerce gateway, product audit, and catalog policies.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Commerce\Application\BulkCatalog;
use QueryNova\Modules\Commerce\Application\CommercePolicies;
use QueryNova\Modules\Commerce\Application\ProductAuditor;
use QueryNova\Modules\Commerce\Domain\CatalogProduct;
use QueryNova\Modules\Commerce\Infrastructure\ProductSnapshotStore;
use QueryNova\Modules\Commerce\Infrastructure\WooCommerceGateway;
use QueryNova\Modules\Commerce\Infrastructure\WooCommerceProductMapper;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;
use QueryNova\Tests\Support\ArrayCommerceApi;
use QueryNova\Tests\Support\FakeWcProduct;

final class CommerceTest extends TestCase {

    public function testInactiveGatewayDoesNotReadOrders(): void {
        $api     = new ArrayCommerceApi( false );
        $gateway = new WooCommerceGateway( $api );
        $page    = $gateway->products( 1, 100 );

        self::assertFalse( $page->active );
        self::assertNull( $page->total );
        self::assertSame( [], $page->products );
        self::assertSame( 50, $page->perPage );
        self::assertNull( $gateway->orderCount() );
        self::assertSame( 0, $api->orderCalls );
        self::assertNull( $gateway->categories( 1, 20 )->total );
    }

    public function testGatewayMapsProductsAndOrderTotalsThroughTheApi(): void {
        $product = new FakeWcProduct(
            [
                'id'                => 9,
                'name'              => 'Alpha 200',
                'short_description' => '<p>Short</p>',
                'description'       => 'A measured description.',
                'url'               => 'https://example.com/alpha',
                'sku'               => 'A200',
                'price'             => '19.50',
                'stock_status'      => 'instock',
                'image_id'          => 4,
                'gallery'           => [ 4, 8 ],
                'attributes'        => [ 'pa_brand' => 'Northwind' ],
                'gtin'              => '000123',
                'categories'        => [ 3 ],
                'review_count'      => 2,
                'rating'            => '4.5',
                'meta'              => [ '_mpn' => 'MPN-1' ],
            ]
        );
        $api     = new ArrayCommerceApi(
            true,
            (object) [
                'products' => [ $product ],
                'total'    => 12,
            ],
            (object) [ 'total' => 4 ],
            [
                [
                    'id'          => 3,
                    'name'        => 'Welders',
                    'slug'        => 'welders',
                    'description' => 'Shop welders',
                    'count'       => 6,
                    'parent'      => 0,
                    'taxonomy'    => 'product_cat',
                ],
                [
                    'id'          => 8,
                    'name'        => 'Northwind',
                    'slug'        => 'northwind',
                    'description' => '',
                    'count'       => 2,
                    'parent'      => 0,
                    'taxonomy'    => 'product_brand',
                ],
            ]
        );
        $gateway = new WooCommerceGateway(
            $api,
            new WooCommerceProductMapper(
                static function ( int $imageId ): array {
                    return [
                        'alt'  => $imageId === 8 ? '' : 'Welder',
                        'file' => 'welder-' . $imageId . '.jpg',
                    ];
                }
            )
        );
        $page    = $gateway->products( 2, 20 );
        $mapped  = $page->products[0];

        self::assertTrue( $page->active );
        self::assertSame( 12, $page->total );
        self::assertSame( 20, $api->productArgs['limit'] );
        self::assertSame( 'Northwind', $mapped->brand );
        self::assertSame( '000123', $mapped->gtin );
        self::assertSame( '19.50', $mapped->price );
        self::assertSame( 4.5, $mapped->rating );
        self::assertSame( 2, $mapped->imageCount );
        self::assertSame( 1, $mapped->missingAltCount );
        self::assertSame( 4, $gateway->orderCount() );
        self::assertSame( 'Welders', $gateway->categories( 1, 20 )->terms[0]->name );
        self::assertSame( 'Northwind', $gateway->brands( 1, 20 )->terms[0]->name );
        self::assertSame( 1, $api->orderCalls );
    }

    public function testOrderAccessDoesNotQueryOrderTables(): void {
        $api     = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Commerce/Infrastructure/WooCommerceApi.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        $gateway = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Commerce/Infrastructure/WooCommerceGateway.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wc_orders', $api . $gateway );
        self::assertStringNotContainsString( '$wpdb', $api . $gateway );
        self::assertStringContainsString( 'wc_get_orders', $api );
    }

    public function testAuditKeepsUnknownReviewsAndRatingsUnset(): void {
        $auditor  = new ProductAuditor();
        $unknown  = $this->product(
            [
                'review_count'      => null,
                'rating'            => null,
                'sku'               => '',
                'brand'             => '',
                'image_count'       => 1,
                'missing_alt_count' => 1,
            ]
        );
        $codes    = array_column( $auditor->audit( $unknown ), 'code' );
        $merchant = $auditor->merchant( $unknown, null, null, null );

        self::assertContains( 'missing_sku', $codes );
        self::assertContains( 'missing_alt', $codes );
        self::assertContains( 'reviews_unavailable', $codes );
        self::assertNotContains( 'missing_rating', $codes );
        self::assertNull( $unknown->rating );
        self::assertNull( $merchant['shipping'] );
        self::assertNull( $merchant['schema'] );
        self::assertFalse( $merchant['brand'] );
    }

    public function testPoliciesDoNotAutoChangeIndexability(): void {
        $policies    = new CommercePolicies();
        $variation   = $this->product(
            [
				'parent_id'     => 4,
				'variation_ids' => [],
			]
        );
        $stock       = $policies->outOfStock( $this->product( [ 'availability' => 'outofstock' ] ) );
        $retired     = $policies->discontinued( $this->product( [ 'discontinued' => true ] ), null );
        $facet       = $policies->facet( 'power', 8, null, true, true, true );
        $ready       = $policies->facet( 'power', 8, 40, true, true, true );
        $mismatch    = $policies->pageTypeMismatch( 'category', 'article' );
        $unknownType = $policies->pageTypeMismatch( '', 'article' );
        $landing     = $policies->landing( '/mig-aparati', '200A', true );

        self::assertFalse( $policies->variation( $variation, null )['index_separately'] );
        self::assertFalse( $policies->variation( $variation, null )['landing'] );
        self::assertTrue( $policies->variation( $variation, 12 )['landing'] );
        self::assertFalse( $stock['auto_noindex'] );
        self::assertNull( $stock['revenue'] );
        self::assertSame( 'keep', $retired['suggested'] );
        self::assertFalse( $retired['applied'] );
        self::assertSame( 'noindex', $facet['choice'] );
        self::assertSame( 'indexable_landing', $ready['choice'] );
        self::assertNotNull( $mismatch );
        self::assertSame( 'page_type_mismatch', $mismatch['code'] );
        self::assertNull( $unknownType );
        self::assertSame( '/mig-aparati/200a/', $landing['path'] );
        self::assertSame( 'suggestion', $landing['status'] );
        self::assertNull( $policies->landing( '/mig-aparati', '200A', false ) );
    }

    public function testOpportunityAndReviewsStayUnavailableWithoutMeasurements(): void {
        $policies = new CommercePolicies();
        $missing  = $policies->opportunity(
            [
                'impressions' => 100,
                'revenue'     => null,
            ]
        );
        $present  = $policies->opportunity(
            [
                'impressions' => 100,
                'revenue'     => 0,
            ]
        );
        $empty    = $policies->reviews( [] );
        $measured = $policies->reviews(
            [
                'The torch lasted through a long job.',
                'Does the torch fit a compact handle?',
            ]
        );
        $clusters = $policies->clusters( [ 'alpha 200', 'buy alpha', 'northwind alpha' ], [ 'northwind' ] );

        self::assertSame( 'unavailable', $missing['status'] );
        self::assertSame( [ 'revenue' ], $missing['missing'] );
        self::assertSame( 'measured', $present['status'] );
        self::assertStringContainsString( 'not a ranking score', $present['note'] );
        self::assertSame( 'unavailable', $empty['status'] );
        self::assertNull( $empty['questions'] );
        self::assertSame( 'measured', $measured['status'] );
        self::assertCount( 1, $measured['questions'] );
        self::assertContains( 'torch', $measured['repeated'] );
        self::assertContains( 'alpha 200', $clusters['primary'] );
        self::assertContains( 'buy alpha', $clusters['transactional'] );
        self::assertContains( 'northwind alpha', $clusters['brand'] );
    }

    public function testBulkImportUpdatesFieldsAndLeavesAnalyticsEmpty(): void {
        $meta = new ArrayMetaStore();
        $bulk = new BulkCatalog( new SeoMetaService( $meta, new TemplateRenderer() ), $meta );
        $bulk->import( "entity_type,entity_id,seo_title,description,primary_keyword,indexability\nproduct,5,Welder,Shop description,mig,index\n" );
        $bulk->import( "entity_type,entity_id,seo_title,description,primary_keyword,indexability\nproduct,5,,,spare,\n" );
        $csv = $bulk->export(
            [
				$this->product(
                    [
						'id'        => 5,
						'seo_title' => null,
                    ]
				),
			]
        );

        self::assertSame( 'Welder', $meta->get( 'product', 5, 'title' ) );
        self::assertSame( 'spare', $meta->get( 'product', 5, 'primary_keyword' ) );
        self::assertStringContainsString( 'unavailable', $csv );
        self::assertStringNotContainsString( ',0,0,0,', $csv );
        self::assertNull( $bulk->rows( [ $this->product( [ 'id' => 5 ] ) ] )[0]['revenue'] );
    }

    public function testSnapshotKeepsAnUnknownPriceNull(): void {
        $database = new ArrayDatabase();
        ( new ProductSnapshotStore( $database ) )->save(
            $this->product(
                [
					'price' => null,
					'id'    => 7,
				]
            )
        );
        $rows = $database->select( 'wp_qn_products', [ 'product_id' => 7 ], 1 );

        self::assertNull( $rows[0]['price'] );
        self::assertSame( 'unknown', $rows[0]['indexability'] );
    }

    public function testSafeModeOmitsCommerce(): void {
        $names = [];
        foreach ( ModuleCatalog::modules( false ) as $module ) {
            $names[] = $module->getName();
        }
        $safe = [];
        foreach ( ModuleCatalog::modules( true ) as $module ) {
            $safe[] = $module->getName();
        }

        self::assertContains( 'commerce', $names );
        self::assertNotContains( 'commerce', $safe );
    }

    public function testFeedFlagsMissingMeasuredFields(): void {
        $issues = ( new ProductAuditor() )->feed(
            $this->product(
                [
					'price'       => null,
					'image_count' => 0,
					'gtin'        => '',
					'description' => 'short',
				]
            )
        );
        $codes  = array_column( $issues, 'code' );

        self::assertContains( 'missing_price', $codes );
        self::assertContains( 'missing_image', $codes );
        self::assertContains( 'missing_identifier', $codes );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function product( array $overrides ): CatalogProduct {
        return CatalogProduct::fromArray(
            array_merge(
                [
                    'id'                => 1,
                    'name'              => 'Alpha 200',
                    'short_description' => 'Short copy',
                    'description'       => 'A long description for the feed row.',
                    'url'               => 'https://example.com/alpha',
                    'sku'               => 'A200',
                    'brand'             => 'Northwind',
                    'gtin'              => '000123',
                    'price'             => '19.50',
                    'availability'      => 'instock',
                    'image_count'       => 1,
                    'missing_alt_count' => 0,
                    'category_ids'      => [ 3 ],
                    'review_count'      => 0,
                ],
                $overrides
            )
        );
    }
}
