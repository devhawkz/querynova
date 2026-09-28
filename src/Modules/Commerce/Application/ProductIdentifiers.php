<?php
/**
 * GTIN family, MPN, ISBN, and per-variation identifiers for one product.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Application;

use QueryNova\Modules\Commerce\Domain\CatalogProduct;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductIdentifiers {

    /**
     * @return list<string>
     */
    public static function keys(): array {
        return [ 'gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'ean', 'upc', 'mpn', 'isbn' ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, string>
     */
    public static function family( CatalogProduct $product, array $extra = [] ): array {
        $fields = [
            'gtin'   => $product->gtin,
            'gtin8'  => '',
            'gtin12' => '',
            'gtin13' => '',
            'gtin14' => '',
            'ean'    => $product->ean,
            'upc'    => $product->upc,
            'mpn'    => $product->mpn,
            'isbn'   => $product->isbn,
        ];
        foreach ( self::keys() as $key ) {
            if ( is_string( $extra[ $key ] ?? null ) ) {
                $fields[ $key ] = trim( wp_strip_all_tags( $extra[ $key ] ) );
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    public static function normalize( array $fields ): array {
        $stored = [];
        foreach ( self::keys() as $key ) {
            $stored[ $key ] = is_string( $fields[ $key ] ?? null ) ? trim( wp_strip_all_tags( $fields[ $key ] ) ) : '';
        }

        return $stored;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{id: int, sku: string, gtin: string, mpn: string, isbn: string}>
     */
    public static function variations( array $rows ): array {
        $clean = [];
        foreach ( $rows as $row ) {
            $item = [
                'id'   => (int) ( $row['id'] ?? 0 ),
                'sku'  => trim( wp_strip_all_tags( (string) ( $row['sku'] ?? '' ) ) ),
                'gtin' => trim( wp_strip_all_tags( (string) ( $row['gtin'] ?? '' ) ) ),
                'mpn'  => trim( wp_strip_all_tags( (string) ( $row['mpn'] ?? '' ) ) ),
                'isbn' => trim( wp_strip_all_tags( (string) ( $row['isbn'] ?? '' ) ) ),
            ];
            if ( $item['sku'] === '' && $item['gtin'] === '' && $item['mpn'] === '' && $item['isbn'] === '' ) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Writes meta for one product only after confirmation, and only when WordPress meta is available.
     * The product URL is not changed.
     *
     * @param array<string, mixed>       $fields
     * @param list<array<string, mixed>> $variations
     * @return array<string, mixed>
     */
    public static function apply( int $productId, array $fields, array $variations, bool $confirmed ): array {
        $stored   = self::normalize( $fields );
        $variants = self::variations( $variations );
        $result   = [
            'product_id'  => $productId,
            'identifiers' => $stored,
            'variations'  => $variants,
            'written'     => false,
            'url_changed' => false,
            'note'        => 'Identifiers are not written until you confirm one product.',
        ];
        if ( ! $confirmed || $productId < 1 || ! function_exists( 'update_post_meta' ) ) {
            if ( $confirmed && ! function_exists( 'update_post_meta' ) ) {
                $result['note'] = 'WordPress meta is unavailable, so identifiers were not written.';
            }

            return $result;
        }
        foreach ( self::keys() as $key ) {
            update_post_meta( $productId, '_querynova_' . $key, $stored[ $key ] );
        }
        update_post_meta( $productId, '_querynova_variation_identifiers', wp_json_encode( $variants ) );
        $result['written'] = true;
        $result['note']    = 'Identifiers were stored for this product. The product URL was not changed.';

        return $result;
    }
}
