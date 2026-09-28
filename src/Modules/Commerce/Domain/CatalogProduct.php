<?php
/**
 * One product snapshot. Missing prices, ratings, and counts stay null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CatalogProduct {

    /**
     * @param list<string> $filenames
     * @param list<int>    $categoryIds
     * @param list<string> $categoryNames
     * @param list<string> $attributes
     * @param list<int>    $variationIds
     * @param list<string> $reviews
     * @param list<array{id: int, sku: string, gtin: string, mpn: string, isbn: string}> $variationIdentifiers
     */
    public function __construct(
        public readonly int $id,
        public readonly int $parentId,
        public readonly string $name,
        public readonly string $shortDescription,
        public readonly string $description,
        public readonly string $url,
        public readonly string $sku,
        public readonly string $brand,
        public readonly string $gtin,
        public readonly string $ean,
        public readonly string $upc,
        public readonly string $mpn,
        public readonly ?string $price,
        public readonly ?string $salePrice,
        public readonly string $currency,
        public readonly string $availability,
        public readonly string $condition,
        public readonly ?int $imageCount,
        public readonly ?int $missingAltCount,
        public readonly array $filenames,
        public readonly array $categoryIds,
        public readonly array $categoryNames,
        public readonly array $attributes,
        public readonly array $variationIds,
        public readonly array $reviews,
        public readonly ?float $rating,
        public readonly ?int $reviewCount,
        public readonly ?int $upsellCount,
        public readonly ?int $crossSellCount,
        public readonly ?int $relatedCount,
        public readonly bool $discontinued,
        public readonly ?string $indexability,
        public readonly ?string $canonical,
        public readonly ?string $seoTitle,
        public readonly ?string $seoDescription,
        public readonly string $isbn = '',
        public readonly array $variationIdentifiers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray( array $row ): self {
        return new self(
            (int) ( $row['id'] ?? 0 ),
            (int) ( $row['parent_id'] ?? 0 ),
            (string) ( $row['name'] ?? '' ),
            (string) ( $row['short_description'] ?? '' ),
            (string) ( $row['description'] ?? '' ),
            (string) ( $row['url'] ?? '' ),
            (string) ( $row['sku'] ?? '' ),
            (string) ( $row['brand'] ?? '' ),
            (string) ( $row['gtin'] ?? '' ),
            (string) ( $row['ean'] ?? '' ),
            (string) ( $row['upc'] ?? '' ),
            (string) ( $row['mpn'] ?? '' ),
            self::nullableString( $row['price'] ?? null ),
            self::nullableString( $row['sale_price'] ?? null ),
            (string) ( $row['currency'] ?? '' ),
            (string) ( $row['availability'] ?? '' ),
            (string) ( $row['condition'] ?? '' ),
            self::nullableInt( $row['image_count'] ?? null ),
            self::nullableInt( $row['missing_alt_count'] ?? null ),
            self::strings( $row['filenames'] ?? null ),
            self::ints( $row['category_ids'] ?? null ),
            self::strings( $row['category_names'] ?? null ),
            self::strings( $row['attributes'] ?? null ),
            self::ints( $row['variation_ids'] ?? null ),
            self::strings( $row['reviews'] ?? null ),
            self::nullableFloat( $row['rating'] ?? null ),
            self::nullableInt( $row['review_count'] ?? null ),
            self::nullableInt( $row['upsell_count'] ?? null ),
            self::nullableInt( $row['cross_sell_count'] ?? null ),
            self::nullableInt( $row['related_count'] ?? null ),
            (bool) ( $row['discontinued'] ?? false ),
            self::nullableString( $row['indexability'] ?? null ),
            self::nullableString( $row['canonical'] ?? null ),
            self::nullableString( $row['seo_title'] ?? null ),
            self::nullableString( $row['seo_description'] ?? null ),
            (string) ( $row['isbn'] ?? '' ),
            self::variationIdentifiers( $row['variation_identifiers'] ?? null )
        );
    }

    public function identifier(): string {
        foreach ( [ $this->gtin, $this->ean, $this->upc, $this->isbn ] as $value ) {
            if ( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }

    private static function nullableString( mixed $value ): ?string {
        if ( $value === null ) {
            return null;
        }

        return trim( (string) $value );
    }

    private static function nullableInt( mixed $value ): ?int {
        if ( $value === null || $value === '' ) {
            return null;
        }

        return (int) $value;
    }

    private static function nullableFloat( mixed $value ): ?float {
        if ( $value === null || $value === '' || ! is_numeric( $value ) ) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return list<string>
     */
    private static function strings( mixed $values ): array {
        if ( ! is_array( $values ) ) {
            return [];
        }
        $rows = [];
        foreach ( $values as $value ) {
            if ( is_string( $value ) && $value !== '' ) {
                $rows[] = $value;
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function ints( mixed $values ): array {
        if ( ! is_array( $values ) ) {
            return [];
        }
        $rows = [];
        foreach ( $values as $value ) {
            if ( is_int( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
                $rows[] = (int) $value;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, sku: string, gtin: string, mpn: string, isbn: string}>
     */
    private static function variationIdentifiers( mixed $values ): array {
        if ( ! is_array( $values ) ) {
            return [];
        }
        $rows = [];
        foreach ( $values as $value ) {
            if ( ! is_array( $value ) ) {
                continue;
            }
            $sku  = trim( (string) ( $value['sku'] ?? '' ) );
            $gtin = trim( (string) ( $value['gtin'] ?? '' ) );
            $mpn  = trim( (string) ( $value['mpn'] ?? '' ) );
            $isbn = trim( (string) ( $value['isbn'] ?? '' ) );
            if ( $sku === '' && $gtin === '' && $mpn === '' && $isbn === '' ) {
                continue;
            }
            $rows[] = [
                'id'   => (int) ( $value['id'] ?? 0 ),
                'sku'  => $sku,
                'gtin' => $gtin,
                'mpn'  => $mpn,
                'isbn' => $isbn,
            ];
        }

        return $rows;
    }
}
