<?php
/**
 * Variation rows that can become Product nodes. Duplicates and empty rows are dropped.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class VariationSchema {

    /**
     * @param list<array<string, mixed>> $variations
     * @return list<array{sku: string, name: string, price: string, currency: string, availability: string, gtin: string, mpn: string, isbn: string}>
     */
    public static function unique( array $variations, string $parentSku, string $parentName, string $parentCurrency = '' ): array {
        $parentSku  = trim( $parentSku );
        $parentName = trim( $parentName );
        $seenSku    = [];
        $seenName   = [];
        $rows       = [];
        foreach ( $variations as $variation ) {
            $sku   = trim( (string) ( $variation['sku'] ?? '' ) );
            $name  = trim( (string) ( $variation['name'] ?? '' ) );
            $price = trim( (string) ( $variation['price'] ?? '' ) );
            if ( $sku === '' && $name === '' && $price === '' ) {
                continue;
            }
            if ( $sku !== '' && $sku === $parentSku && ( $name === '' || $name === $parentName ) ) {
                continue;
            }
            if ( $sku !== '' && isset( $seenSku[ $sku ] ) ) {
                continue;
            }
            if ( $sku === '' && $name !== '' && isset( $seenName[ $name ] ) ) {
                continue;
            }
            if ( $sku !== '' ) {
                $seenSku[ $sku ] = true;
            }
            if ( $name !== '' ) {
                $seenName[ $name ] = true;
            }
            $currency = trim( (string) ( $variation['currency'] ?? '' ) );
            $rows[]   = [
                'sku'          => $sku,
                'name'         => $name,
                'price'        => $price,
                'currency'     => $currency !== '' ? $currency : trim( $parentCurrency ),
                'availability' => trim( (string) ( $variation['availability'] ?? '' ) ),
                'gtin'         => trim( (string) ( $variation['gtin'] ?? '' ) ),
                'mpn'          => trim( (string) ( $variation['mpn'] ?? '' ) ),
                'isbn'         => trim( (string) ( $variation['isbn'] ?? '' ) ),
            ];
        }

        return $rows;
    }
}
