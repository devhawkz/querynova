<?php
/**
 * Reads product facts through WooCommerce functions when that plugin is active.
 *
 * A missing rating stays null. It is not reported as zero.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Infrastructure;

use QueryNova\Modules\Schema\Domain\CommerceFacts;
use QueryNova\Modules\Schema\Domain\CommerceReader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WooCommerceReader implements CommerceReader {

    public function read( int $productId ): ?CommerceFacts {
        if ( $productId <= 0 || ! function_exists( 'wc_get_product' ) ) {
            return null;
        }
        $product = wc_get_product( $productId );
        if ( ! is_object( $product ) ) {
            return null;
        }
        $currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
        $count    = (int) $this->callString( $product, 'get_review_count' );
        $average  = $this->callString( $product, 'get_average_rating' );
        $rating   = null;
        $reviews  = null;
        if ( $count > 0 && is_numeric( $average ) && (float) $average > 0 ) {
            $rating  = (float) $average;
            $reviews = $count;
        }

        return new CommerceFacts(
            $this->callString( $product, 'get_name' ),
            $this->callString( $product, 'get_sku' ),
            $this->callString( $product, 'get_price' ),
            $currency,
            $this->availability( $product ),
            $this->brand( $product, $productId ),
            $rating,
            $reviews,
            $rating === null ? null : 5,
            $this->variations( $product, $currency ),
            $this->reviews( $productId )
        );
    }

    private function availability( object $product ): string {
        $status = $this->callString( $product, 'get_stock_status' );
        $map    = [
            'instock'     => 'https://schema.org/InStock',
            'outofstock'  => 'https://schema.org/OutOfStock',
            'onbackorder' => 'https://schema.org/BackOrder',
        ];

        return $map[ $status ] ?? '';
    }

    private function brand( object $product, int $productId ): string {
        $attribute = $this->callString( $product, 'get_attribute', 'brand' );
        if ( $attribute === '' ) {
            $attribute = $this->callString( $product, 'get_attribute', 'pa_brand' );
        }
        if ( $attribute !== '' || ! function_exists( 'get_the_terms' ) ) {
            return $attribute;
        }
        foreach ( [ 'product_brand', 'pwb-brand', 'yith_product_brand' ] as $taxonomy ) {
            $terms = get_the_terms( $productId, $taxonomy );
            if ( ! is_array( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                if ( $term instanceof \WP_Term && $term->name !== '' ) {
                    return $term->name;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array{sku: string, name: string, price: string, currency: string, availability: string}>
     */
    private function variations( object $product, string $currency ): array {
        if ( $this->callMixed( $product, 'is_type', 'variable' ) !== true ) {
            return [];
        }
        $children = $this->callMixed( $product, 'get_children' );
        if ( ! is_array( $children ) || ! function_exists( 'wc_get_product' ) ) {
            return [];
        }
        $rows = [];
        foreach ( $children as $childId ) {
            $child = wc_get_product( (int) $childId );
            if ( ! is_object( $child ) ) {
                continue;
            }
            $rows[] = [
                'sku'          => $this->callString( $child, 'get_sku' ),
                'name'         => $this->callString( $child, 'get_name' ),
                'price'        => $this->callString( $child, 'get_price' ),
                'currency'     => $currency,
                'availability' => $this->availability( $child ),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{author: string, body: string, rating: float|null}>
     */
    private function reviews( int $productId ): array {
        if ( ! function_exists( 'get_comments' ) ) {
            return [];
        }
        $comments = get_comments(
            [
                'post_id' => $productId,
                'status'  => 'approve',
                'number'  => 5,
                'type'    => 'review',
            ]
        );
        if ( ! is_array( $comments ) ) {
            return [];
        }
        $rows = [];
        foreach ( $comments as $comment ) {
            if ( ! $comment instanceof \WP_Comment ) {
                continue;
            }
            $body   = wp_strip_all_tags( (string) $comment->comment_content );
            $author = wp_strip_all_tags( (string) $comment->comment_author );
            $rating = null;
            if ( function_exists( 'get_comment_meta' ) ) {
                $stored = get_comment_meta( (int) $comment->comment_ID, 'rating', true );
                if ( is_numeric( $stored ) && (float) $stored > 0 ) {
                    $rating = (float) $stored;
                }
            }
            if ( $body === '' ) {
                continue;
            }
            $rows[] = [
                'author' => $author,
                'body'   => $body,
                'rating' => $rating,
            ];
        }

        return $rows;
    }

    private function callMixed( object $target, string $method, string ...$arguments ): mixed {
        if ( ! method_exists( $target, $method ) ) {
            return null;
        }

        return $target->{$method}( ...$arguments );
    }

    private function callString( object $target, string $method, string ...$arguments ): string {
        if ( ! method_exists( $target, $method ) ) {
            return '';
        }
        $value = $target->{$method}( ...$arguments );
        if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
            return trim( (string) $value );
        }

        return '';
    }
}
