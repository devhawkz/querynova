<?php
/**
 * WooCommerce function adapter.
 *
 * Orders are read with wc_get_orders so HPOS and the posts datastore stay inside WooCommerce.
 * This class does not query order tables.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Infrastructure;

use QueryNova\Modules\Commerce\Domain\CommerceApi;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WooCommerceApi implements CommerceApi {

    public function available(): bool {
        return function_exists( 'wc_get_products' );
    }

    public function products( array $args ): mixed {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return null;
        }

        return wc_get_products( $args );
    }

    public function orders( array $args ): mixed {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return null;
        }

        return wc_get_orders( $args );
    }

    public function terms( string $taxonomy ): array {
        if ( ! function_exists( 'get_terms' ) ) {
            return [];
        }
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );
        if ( ! is_array( $terms ) ) {
            return [];
        }
        $rows = [];
        foreach ( $terms as $term ) {
            if ( ! $term instanceof \WP_Term ) {
                continue;
            }
            $rows[] = [
                'id'          => (int) $term->term_id,
                'name'        => (string) $term->name,
                'slug'        => (string) $term->slug,
                'description' => (string) $term->description,
                'count'       => (int) $term->count,
                'parent'      => (int) $term->parent,
                'taxonomy'    => $taxonomy,
            ];
        }

        return $rows;
    }
}
