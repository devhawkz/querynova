<?php
/**
 * Maps a WooCommerce product object without type-hinting WooCommerce classes.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Infrastructure;

use QueryNova\Modules\Commerce\Domain\CatalogProduct;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WooCommerceProductMapper {

    /**
     * @param callable(int): array{alt: ?string, file: string}|null $media
     */
    public function __construct( private readonly mixed $media = null ) {
    }

    public function map( object $product ): CatalogProduct {
        $imageIds = $this->imageIds( $product );
        $alts     = 0;
        $known    = 0;
        $files    = [];
        foreach ( $imageIds as $imageId ) {
            $media = $this->mediaFor( $imageId );
            if ( $media['file'] !== '' ) {
                $files[] = $media['file'];
            }
            if ( $media['alt'] === null ) {
                continue;
            }
            ++$known;
            if ( $media['alt'] === '' ) {
                ++$alts;
            }
        }
        $rating = $this->rating( $product );
        $parent = (int) $this->call( $product, 'get_parent_id' );

        return new CatalogProduct(
            (int) $this->call( $product, 'get_id' ),
            $parent > 0 ? $parent : 0,
            $this->text( $product, 'get_name' ),
            $this->plain( $this->text( $product, 'get_short_description' ) ),
            $this->plain( $this->text( $product, 'get_description' ) ),
            $this->text( $product, 'get_permalink' ),
            $this->text( $product, 'get_sku' ),
            $this->brand( $product ),
            $this->gtin( $product ),
            $this->meta( $product, '_ean' ),
            $this->meta( $product, '_upc' ),
            $this->meta( $product, '_mpn' ),
            $this->money( $product, 'get_price' ),
            $this->money( $product, 'get_sale_price' ),
            function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '',
            $this->text( $product, 'get_stock_status' ),
            '',
            $imageIds === [] ? 0 : count( $imageIds ),
            $known === 0 && $imageIds !== [] ? null : $alts,
            $files,
            $this->ints( $this->call( $product, 'get_category_ids' ) ),
            [],
            $this->attributeNames( $product ),
            $this->ints( $this->call( $product, 'get_children' ) ),
            [],
            $rating['rating'],
            $rating['count'],
            $this->countOf( $product, 'get_upsell_ids' ),
            $this->countOf( $product, 'get_cross_sell_ids' ),
            null,
            false,
            null,
            null,
            null,
            null
        );
    }

    /**
     * @return list<int>
     */
    private function imageIds( object $product ): array {
        $ids      = [];
        $featured = (int) $this->call( $product, 'get_image_id' );
        if ( $featured > 0 ) {
            $ids[] = $featured;
        }
        foreach ( $this->ints( $this->call( $product, 'get_gallery_image_ids' ) ) as $id ) {
            if ( ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array{alt: ?string, file: string}
     */
    private function mediaFor( int $imageId ): array {
        if ( is_callable( $this->media ) ) {
            $media = ( $this->media )( $imageId );
            if ( is_array( $media ) ) {
                $alt = $media['alt'] ?? null;
                if ( ! is_string( $alt ) ) {
                    $alt = null;
                }

                return [
                    'alt'  => $alt,
                    'file' => is_string( $media['file'] ?? null ) ? $media['file'] : '',
                ];
            }
        }
        $alt  = null;
        $file = '';
        if ( function_exists( 'get_post_meta' ) ) {
            $raw = get_post_meta( $imageId, '_wp_attachment_image_alt', true );
            $alt = is_string( $raw ) ? trim( $raw ) : '';
        }
        if ( function_exists( 'get_attached_file' ) ) {
            $path = get_attached_file( $imageId );
            $file = is_string( $path ) ? basename( $path ) : '';
        }

        return [
            'alt'  => $alt,
            'file' => $file,
        ];
    }

    private function brand( object $product ): string {
        foreach ( [ 'brand', 'pa_brand' ] as $attribute ) {
            $value = $this->text( $product, 'get_attribute', $attribute );
            if ( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }

    private function gtin( object $product ): string {
        $modern = $this->text( $product, 'get_global_unique_id' );
        if ( $modern !== '' ) {
            return $modern;
        }
        foreach ( [ '_global_unique_id', '_gtin' ] as $key ) {
            $value = $this->meta( $product, $key );
            if ( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{rating: ?float, count: ?int}
     */
    private function rating( object $product ): array {
        if ( ! method_exists( $product, 'get_review_count' ) ) {
            return [
                'rating' => null,
                'count'  => null,
            ];
        }
        $count = $this->call( $product, 'get_review_count' );
        if ( ! is_numeric( $count ) ) {
            return [
                'rating' => null,
                'count'  => null,
            ];
        }
        $count   = (int) $count;
        $average = $this->call( $product, 'get_average_rating' );
        $rating  = null;
        if ( $count > 0 && is_numeric( $average ) && (float) $average > 0 ) {
            $rating = (float) $average;
        }

        return [
            'rating' => $rating,
            'count'  => $count,
        ];
    }

    /**
     * @return list<string>
     */
    private function attributeNames( object $product ): array {
        $names = [];
        foreach ( [ 'brand', 'pa_brand', 'pa_color', 'pa_size' ] as $attribute ) {
            $value = $this->text( $product, 'get_attribute', $attribute );
            if ( $value !== '' ) {
                $names[] = $attribute;
            }
        }

        return $names;
    }

    private function countOf( object $product, string $method ): ?int {
        if ( ! method_exists( $product, $method ) ) {
            return null;
        }
        $value = $product->{$method}();

        return is_array( $value ) ? count( $value ) : null;
    }

    private function money( object $product, string $method ): ?string {
        $value = $this->text( $product, $method );

        return $value === '' ? null : $value;
    }

    private function meta( object $product, string $key ): string {
        if ( ! method_exists( $product, 'get_meta' ) ) {
            return '';
        }
        $value = $product->get_meta( $key );
        if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
            return trim( (string) $value );
        }

        return '';
    }

    private function text( object $product, string $method, string ...$arguments ): string {
        $value = $this->call( $product, $method, ...$arguments );
        if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
            return trim( (string) $value );
        }

        return '';
    }

    private function call( object $product, string $method, string ...$arguments ): mixed {
        if ( ! method_exists( $product, $method ) ) {
            return null;
        }

        return $product->{$method}( ...$arguments );
    }

    private function plain( string $html ): string {
        if ( function_exists( 'wp_strip_all_tags' ) ) {
            return trim( wp_strip_all_tags( $html ) );
        }

        return trim( $html );
    }

    /**
     * @return list<int>
     */
    private function ints( mixed $values ): array {
        if ( ! is_array( $values ) ) {
            return [];
        }
        $rows = [];
        foreach ( $values as $value ) {
            if ( is_numeric( $value ) ) {
                $rows[] = (int) $value;
            }
        }

        return $rows;
    }
}
