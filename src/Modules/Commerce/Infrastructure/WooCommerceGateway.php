<?php
/**
 * Commerce gateway. Catalog and order reads go through WooCommerce APIs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Infrastructure;

use QueryNova\Modules\Commerce\Domain\CatalogTerm;
use QueryNova\Modules\Commerce\Domain\CommerceApi;
use QueryNova\Modules\Commerce\Domain\ProductPage;
use QueryNova\Modules\Commerce\Domain\TermPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WooCommerceGateway {

    private const BRAND_TAXONOMIES = [ 'product_brand', 'pwb-brand', 'yith_product_brand', 'pa_brand' ];

    public function __construct(
        private readonly CommerceApi $api,
        private readonly WooCommerceProductMapper $mapper = new WooCommerceProductMapper(),
    ) {
    }

    public function active(): bool {
        return $this->api->available();
    }

    public function products( int $page, int $perPage ): ProductPage {
        $page    = max( 1, $page );
        $perPage = max( 1, min( 50, $perPage ) );
        if ( ! $this->active() ) {
            return new ProductPage( [], null, $page, $perPage, false );
        }
        $result = $this->api->products(
            [
                'status'   => 'publish',
                'limit'    => $perPage,
                'page'     => $page,
                'paginate' => true,
                'return'   => 'objects',
            ]
        );
        $list   = [];
        $total  = null;
        if ( is_object( $result ) ) {
            $raw = $result->products ?? null;
            if ( is_array( $raw ) ) {
                $list = $raw;
            }
            if ( isset( $result->total ) && is_numeric( $result->total ) ) {
                $total = (int) $result->total;
            }
        }

        $products = [];
        foreach ( $list as $product ) {
            if ( is_object( $product ) ) {
                $products[] = $this->mapper->map( $product );
            }
        }

        return new ProductPage( $products, $total, $page, $perPage, true );
    }

    /**
     * A paginated order total from WooCommerce. A missing API stays null, not zero.
     */
    public function orderCount(): ?int {
        if ( ! $this->active() ) {
            return null;
        }
        $result = $this->api->orders(
            [
                'limit'    => 1,
                'paginate' => true,
                'return'   => 'ids',
            ]
        );
        if ( is_object( $result ) && isset( $result->total ) && is_numeric( $result->total ) ) {
            return (int) $result->total;
        }

        return null;
    }

    public function categories( int $page, int $perPage ): TermPage {
        return $this->terms( 'product_cat', $page, $perPage );
    }

    public function brands( int $page, int $perPage ): TermPage {
        if ( ! $this->active() ) {
            return new TermPage( [], null, false );
        }
        $terms = [];
        foreach ( self::BRAND_TAXONOMIES as $taxonomy ) {
            foreach ( $this->api->terms( $taxonomy ) as $row ) {
                $terms[] = $this->term( $row );
            }
        }
        $page    = max( 1, $page );
        $perPage = max( 1, min( 50, $perPage ) );
        $slice   = array_slice( $terms, ( $page - 1 ) * $perPage, $perPage );

        return new TermPage( $slice, count( $terms ), true );
    }

    private function terms( string $taxonomy, int $page, int $perPage ): TermPage {
        if ( ! $this->active() ) {
            return new TermPage( [], null, false );
        }
        $terms = [];
        foreach ( $this->api->terms( $taxonomy ) as $row ) {
            $terms[] = $this->term( $row );
        }
        $page    = max( 1, $page );
        $perPage = max( 1, min( 50, $perPage ) );

        return new TermPage( array_slice( $terms, ( $page - 1 ) * $perPage, $perPage ), count( $terms ), true );
    }

    /**
     * @param array{id: int, name: string, slug: string, description: string, count: int, parent: int, taxonomy: string} $row
     */
    private function term( array $row ): CatalogTerm {
        return new CatalogTerm(
            $row['id'],
            $row['taxonomy'],
            $row['name'],
            $row['slug'],
            $row['description'],
            $row['count'],
            $row['parent']
        );
    }
}
