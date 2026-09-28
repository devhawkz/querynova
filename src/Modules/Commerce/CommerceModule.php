<?php
/**
 * Commerce SEO. The workspace stays inactive until WooCommerce is active.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Commerce\Application\BulkCatalog;
use QueryNova\Modules\Commerce\Application\CatalogWorkspace;
use QueryNova\Modules\Commerce\Application\CommercePolicies;
use QueryNova\Modules\Commerce\Application\ProductAuditor;
use QueryNova\Modules\Commerce\Application\ProductIdentifiers;
use QueryNova\Modules\Commerce\Application\UrlBaseRewrite;
use QueryNova\Modules\Commerce\Infrastructure\ProductSnapshotStore;
use QueryNova\Modules\Commerce\Infrastructure\WooCommerceApi;
use QueryNova\Modules\Commerce\Infrastructure\WooCommerceGateway;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\WordPressMetaStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CommerceModule extends AbstractModule {

    private ?WooCommerceGateway $gateway = null;

    private ?BulkCatalog $bulk = null;

    private ?ProductSnapshotStore $snapshots = null;

    private ?DatabaseConnection $database = null;

    private ProductAuditor $auditor;

    private CommercePolicies $policies;

    public function __construct() {
        $this->auditor  = new ProductAuditor();
        $this->policies = new CommercePolicies();
    }

    public function getName(): string {
        return 'commerce';
    }

    public function getDependencies(): array {
        return [ 'core', 'seo' ];
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.commerce',
                    'Commerce SEO',
                    'commerce',
                    QUERYNOVA_VERSION,
                    [ 'querynova.seo' ],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::MANAGE_WOOCOMMERCE_SEO ],
                )
            );
        }
        $seo = $container->has( SeoMetaService::class ) ? $container->get( SeoMetaService::class ) : null;
        if ( ! $seo instanceof SeoMetaService ) {
            $seo = new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() );
        }
        $database        = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->database  = $database;
        $this->gateway   = new WooCommerceGateway( new WooCommerceApi() );
        $this->bulk      = new BulkCatalog( $seo, new WordPressMetaStore() );
        $this->snapshots = new ProductSnapshotStore( $database );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $capability = Capability::MANAGE_WOOCOMMERCE_SEO;
        $rest->route( 'GET', '/commerce/status', [ $this, 'status' ], $capability );
        $rest->route( 'GET', '/commerce/products', [ $this, 'products' ], $capability );
        $rest->route( 'GET', '/commerce/categories', [ $this, 'categories' ], $capability );
        $rest->route( 'GET', '/commerce/brands', [ $this, 'brands' ], $capability );
        $rest->route( 'GET', '/commerce/bulk', [ $this, 'bulkPage' ], $capability );
        $rest->route( 'GET', '/commerce/bulk/export', [ $this, 'export' ], $capability );
        $rest->route( 'POST', '/commerce/bulk/import', [ $this, 'import' ], $capability );
        $rest->route( 'GET', '/commerce/workspace', [ $this, 'workspace' ], $capability );
        $rest->route( 'POST', '/commerce/identifiers', [ $this, 'identifiers' ], $capability );
        $rest->route( 'POST', '/commerce/facets', [ $this, 'facets' ], $capability );
        $rest->route( 'POST', '/commerce/url-base', [ $this, 'urlBase' ], $capability );
    }

    /**
     * @return array<string, mixed>
     */
    public function status( \WP_REST_Request $request ): array {
        unset( $request );
        $gateway = $this->gateway();

        return [
            'active' => $gateway->active(),
            'orders' => $gateway->orderCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function products( \WP_REST_Request $request ): array {
        $page = $this->gateway()->products( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );
        $rows = [];
        foreach ( $page->products as $product ) {
            $this->snapshots?->save( $product );
            $rows[] = [
                'id'       => $product->id,
                'name'     => $product->name,
                'sku'      => $product->sku,
                'audit'    => $this->auditor->audit( $product ),
                'intent'   => $this->policies->intent( $product ),
                'merchant' => $this->auditor->merchant( $product, null, null, null ),
            ];
        }

        return [
            'active'   => $page->active,
            'total'    => $page->total,
            'page'     => $page->page,
            'per_page' => $page->perPage,
            'products' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function categories( \WP_REST_Request $request ): array {
        $page = $this->gateway()->categories( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );

        return [
            'active' => $page->active,
            'total'  => $page->total,
            'terms'  => $this->termRows( $page->terms ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function brands( \WP_REST_Request $request ): array {
        $page = $this->gateway()->brands( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );

        return [
            'active' => $page->active,
            'total'  => $page->total,
            'terms'  => $this->termRows( $page->terms ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bulkPage( \WP_REST_Request $request ): array {
        $page = $this->gateway()->products( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );

        return [
            'active' => $page->active,
            'total'  => $page->total,
            'rows'   => $this->bulk instanceof BulkCatalog ? $this->bulk->rows( $page->products ) : [],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function export( \WP_REST_Request $request ): array {
        $page = $this->gateway()->products( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );

        return [
            'csv' => $this->bulk instanceof BulkCatalog ? $this->bulk->export( $page->products ) : '',
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function import( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->bulk instanceof BulkCatalog ) {
            return new \WP_Error( 'querynova_commerce_unavailable', 'Commerce SEO is unavailable.', [ 'status' => 500 ] );
        }
        $params = $request->get_json_params();
        $csv    = (string) ( $params['csv'] ?? '' );
        try {
            return $this->bulk->import( $csv );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_catalog', $exception->getMessage(), [ 'status' => 400 ] );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace( \WP_REST_Request $request ): array {
        $kind = $request->get_param( 'kind' ) === 'category' ? 'category' : 'product';
        $rows = $kind === 'category' ? $this->storedCategories() : $this->storedProducts();

        return CatalogWorkspace::present(
            $rows,
            is_string( $request->get_param( 'search' ) ) ? (string) $request->get_param( 'search' ) : '',
            is_string( $request->get_param( 'issue' ) ) ? (string) $request->get_param( 'issue' ) : '',
            is_string( $request->get_param( 'opportunity' ) ) ? (string) $request->get_param( 'opportunity' ) : '',
            $kind
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function identifiers( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];
        $rows   = [];
        if ( isset( $params['variations'] ) && is_array( $params['variations'] ) ) {
            foreach ( $params['variations'] as $row ) {
                if ( is_array( $row ) ) {
                    $rows[] = $row;
                }
            }
        }

        return ProductIdentifiers::apply(
            (int) ( $params['product_id'] ?? 0 ),
            $params,
            $rows,
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function facets( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];

        return $this->policies->crawlTrap(
            (int) ( $params['facet_count'] ?? 0 ),
            isset( $params['combination_count'] ) && is_numeric( $params['combination_count'] ) ? (int) $params['combination_count'] : null,
            isset( $params['indexable_count'] ) && is_numeric( $params['indexable_count'] ) ? (int) $params['indexable_count'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function urlBase( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];

        return UrlBaseRewrite::plan(
            is_string( $params['product_base'] ?? null ) ? $params['product_base'] : '',
            is_string( $params['category_base'] ?? null ) ? $params['category_base'] : '',
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return list<array{id: int, name: string, sku: string, issues: list<string>, opportunity: string}>
     */
    private function storedProducts(): array {
        if ( ! $this->database instanceof DatabaseConnection ) {
            return [];
        }
        $rows = [];
        foreach ( $this->database->select( $this->database->prefix() . 'qn_products', [], 20, 0, [ 'id' => 'DESC' ] ) as $product ) {
            $id   = (int) ( $product['product_id'] ?? 0 );
            $sku  = trim( (string) ( $product['sku'] ?? '' ) );
            $gtin = trim( (string) ( $product['gtin'] ?? '' ) );
            if ( $id < 1 && $sku === '' ) {
                continue;
            }
            $rows[] = [
                'id'          => $id,
                'name'        => $sku !== '' ? $sku : 'Product ' . (string) $id,
                'sku'         => $sku,
                'issues'      => $gtin === '' ? [ 'missing_identifier' ] : [],
                'opportunity' => 'unavailable',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string, sku: string, issues: list<string>, opportunity: string}>
     */
    private function storedCategories(): array {
        if ( ! $this->database instanceof DatabaseConnection ) {
            return [];
        }
        $rows = [];
        foreach ( $this->database->select( $this->database->prefix() . 'qn_categories', [], 20, 0, [ 'id' => 'DESC' ] ) as $category ) {
            $id = (int) ( $category['term_id'] ?? 0 );
            if ( $id < 1 ) {
                continue;
            }
            $rows[] = [
                'id'          => $id,
                'name'        => 'Category ' . (string) $id,
                'sku'         => '',
                'issues'      => [],
                'opportunity' => 'unavailable',
            ];
        }

        return $rows;
    }

    private function gateway(): WooCommerceGateway {
        return $this->gateway instanceof WooCommerceGateway ? $this->gateway : new WooCommerceGateway( new WooCommerceApi() );
    }

    /**
     * @param list<\QueryNova\Modules\Commerce\Domain\CatalogTerm> $terms
     * @return list<array<string, mixed>>
     */
    private function termRows( array $terms ): array {
        $rows = [];
        foreach ( $terms as $term ) {
            $rows[] = [
                'id'          => $term->id,
                'taxonomy'    => $term->taxonomy,
                'name'        => $term->name,
                'description' => $term->description,
                'count'       => $term->count,
            ];
        }

        return $rows;
    }
}
