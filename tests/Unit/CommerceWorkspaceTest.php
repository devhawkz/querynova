<?php
/**
 * Product and category workspaces, identifiers, URL bases, and optional EDD.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Commerce\Application\CatalogWorkspace;
use QueryNova\Modules\Commerce\Application\CommercePolicies;
use QueryNova\Modules\Commerce\Application\ProductIdentifiers;
use QueryNova\Modules\Commerce\Application\UrlBaseRewrite;
use QueryNova\Modules\Commerce\CommerceModule;
use QueryNova\Modules\Commerce\Domain\CatalogProduct;
use QueryNova\Modules\Core\CategoryScreen;
use QueryNova\Modules\Core\ProductScreen;
use QueryNova\Modules\Edd\EddModule;
use QueryNova\Modules\Schema\Application\VariationSchema;

final class CommerceWorkspaceTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ UrlBaseRewrite::OPTION ] );
    }

    public function testWorkspaceLeadsWithSearchAndFiltersStoredRows(): void {
        $rows  = [
            [
                'id'          => 4,
                'name'        => 'Lager',
                'sku'         => 'LAG',
                'issues'      => [ 'missing_identifier' ],
                'opportunity' => 'unavailable',
            ],
            [
                'id'          => 9,
                'name'        => 'Pils',
                'sku'         => 'PIL',
                'issues'      => [],
                'opportunity' => 'unavailable',
            ],
        ];
        $empty = CatalogWorkspace::present( [], '', '', '', 'product' );
        $found = CatalogWorkspace::present( $rows, 'lag', 'missing_identifier', 'unavailable', 'product' );
        $miss  = CatalogWorkspace::present( $rows, '', '', 'measured', 'category' );

        self::assertSame( 'Search by name or SKU. Product URLs stay unchanged.', $empty['lead'] );
        self::assertSame( [], $empty['recent'] );
        self::assertFalse( $empty['rewritten'] );
        self::assertStringNotContainsString( 'No stored product', $empty['lead'] );
        self::assertSame( 'Recent products. Product URLs stay unchanged.', $found['lead'] );
        self::assertSame( 4, $found['entities'][0]['id'] );
        self::assertSame( 'Search by category name. Category URLs stay unchanged.', $miss['lead'] );
        self::assertStringNotContainsString( 'No stored category', $miss['lead'] );
    }

    public function testStoredScreensExposeRecentEntities(): void {
        $database = new ArrayDatabase();
        $database->insert(
            'wp_qn_products',
            [
                'product_id' => 4,
                'sku'        => 'LAG',
                'gtin'       => '',
            ]
        );
        $database->insert(
            'wp_qn_products',
            [
                'product_id' => 9,
                'sku'        => 'PIL',
                'gtin'       => '123',
            ]
        );
        $database->insert(
            'wp_qn_categories',
            [
                'term_id' => 3,
            ]
        );
        $product  = ( new ProductScreen() )->fromDatabase( $database );
        $category = ( new CategoryScreen() )->fromDatabase( $database );
        $blank    = ( new ProductScreen() )->fromDatabase( new ArrayDatabase() );

        self::assertSame( 'Recent products. Product URLs stay unchanged.', $product['workspace']['lead'] );
        self::assertSame( 9, $product['workspace']['recent'][0]['id'] );
        self::assertSame( [ 'missing_identifier' ], $product['workspace']['entities'][1]['issues'] );
        self::assertFalse( $product['workspace']['rewritten'] );
        self::assertSame( 'Recent categories. Category URLs stay unchanged.', $category['workspace']['lead'] );
        self::assertSame( 'Category 3', $category['workspace']['recent'][0]['name'] );
        self::assertSame( 'Search by name or SKU. Product URLs stay unchanged.', $blank['workspace']['lead'] );
        self::assertNull( $blank['title'] );
    }

    public function testIdentifiersStayUnwrittenWithoutConfirmationOrMeta(): void {
        $product = CatalogProduct::fromArray(
            [
                'id'   => 7,
                'name' => 'Lager',
                'gtin' => '111',
                'isbn' => '978',
            ]
        );
        $family  = ProductIdentifiers::family(
            $product,
            [
                'mpn'    => '<b>MPN-1</b>',
                'gtin13' => '1234567890123',
            ]
        );
        $held    = ProductIdentifiers::apply(
            7,
            [
                'gtin' => '111',
                'isbn' => '978',
            ],
            [
                [
                    'sku'  => '',
                    'gtin' => '',
                    'mpn'  => '',
                    'isbn' => '',
                ],
            ],
            false
        );
        $missing = ProductIdentifiers::apply( 7, [ 'isbn' => '978' ], [], true );

        self::assertSame( [ 'gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'ean', 'upc', 'mpn', 'isbn' ], ProductIdentifiers::keys() );
        self::assertSame( '111', $family['gtin'] );
        self::assertSame( '978', $family['isbn'] );
        self::assertSame( 'MPN-1', $family['mpn'] );
        self::assertSame( '1234567890123', $family['gtin13'] );
        self::assertFalse( $held['written'] );
        self::assertFalse( $held['url_changed'] );
        self::assertSame( [], $held['variations'] );
        self::assertSame( 'Identifiers are not written until you confirm one product.', $held['note'] );
        self::assertFalse( $missing['written'] );
        self::assertFalse( $missing['url_changed'] );
        self::assertSame( 'WordPress meta is unavailable, so identifiers were not written.', $missing['note'] );
        self::assertSame( '111', $product->identifier() );
        self::assertSame( '978', CatalogProduct::fromArray( [ 'isbn' => '978' ] )->identifier() );
    }

    public function testUrlBaseConfirmationDoesNotRewritePermalinks(): void {
        $held    = UrlBaseRewrite::plan( ' shop ', 'product-category', false );
        $stored  = UrlBaseRewrite::plan( 'shop', 'product-category', true );
        $sources = $this->sources();

        self::assertFalse( $held['applied'] );
        self::assertFalse( $held['flushed'] );
        self::assertFalse( $held['stored'] );
        self::assertSame( 'Product and category URL bases stay unchanged until you confirm.', $held['note'] );
        self::assertFalse( $stored['applied'] );
        self::assertFalse( $stored['flushed'] );
        self::assertTrue( $stored['stored'] );
        self::assertSame( 'The request is stored. Live product and category URLs were not changed.', $stored['note'] );
        self::assertSame(
            [
                'product_base'  => 'shop',
                'category_base' => 'product-category',
            ],
            $GLOBALS['querynova_options'][ UrlBaseRewrite::OPTION ]
        );
        foreach ( $sources as $source ) {
            self::assertStringNotContainsString( 'flush_rewrite_rules', $source );
            self::assertStringNotContainsString( 'woocommerce_permalinks', $source );
            self::assertStringNotContainsString( 'wp_remote_', $source );
            self::assertStringNotContainsString( 'chart.js', $source );
        }
    }

    public function testFacetCrawlTrapDoesNotTreatAMissingCountAsZero(): void {
        $policies = new CommercePolicies();
        $missing  = $policies->crawlTrap( 4, null, null );
        $trap     = $policies->crawlTrap( 2, 50, 1 );
        $clear    = $policies->crawlTrap( 4, 8, null );
        $rows     = VariationSchema::unique(
            [
                [
                    'sku'   => 'PARENT',
                    'name'  => '',
                    'price' => '9',
                ],
                [
                    'sku'      => 'A',
                    'name'     => 'Small',
                    'price'    => '10',
                    'currency' => '',
                ],
                [
                    'sku'   => 'A',
                    'name'  => 'Copy',
                    'price' => '11',
                ],
                [
                    'sku'   => '',
                    'name'  => 'Small',
                    'price' => '12',
                ],
            ],
            'PARENT',
            'Welder',
            'EUR'
        );

        self::assertSame( 'unavailable', $missing['status'] );
        self::assertNull( $missing['indexable'] );
        self::assertFalse( $missing['applied'] );
        self::assertStringContainsString( 'did not crawl', $missing['note'] );
        self::assertSame( 'crawl_trap', $trap['status'] );
        self::assertFalse( $trap['applied'] );
        self::assertSame( 1, $trap['indexable'] );
        self::assertSame( 'clear', $clear['status'] );
        self::assertNull( $clear['indexable'] );
        self::assertCount( 1, $rows );
        self::assertSame( 'A', $rows[0]['sku'] );
        self::assertSame( 'EUR', $rows[0]['currency'] );
    }

    public function testEddStaysOutOfTheCatalogWhenThePluginIsAbsent(): void {
        $rest = new RestRegistrar();
        ( new EddModule() )->registerRoutes( $rest );
        ( new CommerceModule() )->registerRoutes( $rest );
        $names  = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );
        $routes = array_column( $rest->routes(), 'route' );

        self::assertFalse( EddModule::present() );
        self::assertNotContains( 'edd', $names );
        self::assertContains( 'commerce', $names );
        self::assertNotContains( '/edd/status', $routes );
        self::assertContains( '/commerce/workspace', $routes );
        self::assertContains( '/commerce/identifiers', $routes );
        self::assertContains( '/commerce/facets', $routes );
        self::assertContains( '/commerce/url-base', $routes );
    }

    /**
     * @return list<string>
     */
    private function sources(): array {
        $files   = [
            dirname( __DIR__, 2 ) . '/src/Modules/Commerce/Application/UrlBaseRewrite.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Commerce/Application/ProductIdentifiers.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Edd/EddModule.php',
            dirname( __DIR__, 2 ) . '/src/Modules/Schema/Application/VariationSchema.php',
        ];
        $sources = [];
        foreach ( $files as $file ) {
            $contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
            self::assertIsString( $contents );
            $sources[] = $contents;
        }

        return $sources;
    }
}
