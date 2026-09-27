<?php
/**
 * Product, image, merchant, and feed checks. Missing measurements stay null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Application;

use QueryNova\Modules\Commerce\Domain\CatalogProduct;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductAuditor {

    /**
     * @return list<array{code: string, severity: string, detail: string}>
     */
    public function audit( CatalogProduct $product ): array {
        $issues = [];
        $this->add( $issues, $product->name === '', 'missing_name', 'warning', 'The product name is empty.' );
        $this->add( $issues, $product->shortDescription === '', 'missing_short_description', 'warning', 'The short description is empty.' );
        $this->add( $issues, $product->description === '', 'missing_description', 'warning', 'The long description is empty.' );
        $this->add( $issues, $product->sku === '', 'missing_sku', 'warning', 'SKU is empty.' );
        $this->add( $issues, $product->brand === '', 'missing_brand', 'warning', 'Brand is empty.' );
        $this->add( $issues, $product->identifier() === '', 'missing_identifier', 'info', 'GTIN, EAN, and UPC are empty.' );
        $this->add( $issues, $product->categoryIds === [] && $product->categoryNames === [], 'missing_category', 'warning', 'No category is assigned.' );
        $this->add( $issues, $product->imageCount === 0, 'missing_image', 'warning', 'No product image was found.' );
        if ( $product->missingAltCount !== null && $product->missingAltCount > 0 ) {
            $issues[] = $this->issue( 'missing_alt', 'warning', $product->missingAltCount . ' image ALT values are empty.' );
        }
        $this->add( $issues, $product->indexability === 'noindex', 'not_indexable', 'info', 'The product is set to noindex.' );
        if ( $product->canonical !== null && $product->canonical !== '' && $product->url !== '' && $product->canonical !== $product->url ) {
            $issues[] = $this->issue( 'canonical_mismatch', 'warning', 'Canonical points to ' . $product->canonical . '.' );
        }
        if ( $product->parentId > 0 ) {
            $issues[] = $this->issue( 'variation', 'info', 'Variations are not given their own index by default.' );
        }
        if ( $product->reviewCount === null ) {
            $issues[] = $this->issue( 'reviews_unavailable', 'info', 'Review count was not measured. It is not zero.' );
        }

        return $issues;
    }

    /**
     * @return list<array{code: string, severity: string, detail: string}>
     */
    public function images( CatalogProduct $product ): array {
        $issues = [];
        $this->add( $issues, $product->imageCount === 0, 'missing_image', 'warning', 'No product image was found.' );
        if ( $product->missingAltCount !== null && $product->missingAltCount > 0 ) {
            $issues[] = $this->issue( 'missing_alt', 'warning', 'ALT text is missing. QueryNova does not invent ALT text.' );
        }
        $seen = [];
        foreach ( $product->filenames as $filename ) {
            $key = strtolower( $filename );
            if ( isset( $seen[ $key ] ) ) {
                $issues[] = $this->issue( 'duplicate_filename', 'warning', 'Filename ' . $filename . ' is repeated.' );
            }
            $seen[ $key ] = true;
            if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/', strtolower( $filename ) ) ) {
                $issues[] = $this->issue( 'unknown_format', 'info', 'Filename ' . $filename . ' has no recognized image extension.' );
            }
        }

        return $issues;
    }

    /**
     * Shipping, returns, and schema are null when they were not measured.
     *
     * @return array<string, mixed>
     */
    public function merchant( CatalogProduct $product, ?bool $schemaPresent, ?bool $shippingKnown, ?bool $returnsKnown ): array {
        return [
            'gtin'         => $product->identifier() !== '',
            'mpn'          => $product->mpn !== '',
            'brand'        => $product->brand !== '',
            'price'        => $product->price !== null && $product->price !== '',
            'availability' => $product->availability !== '',
            'images'       => $product->imageCount !== null && $product->imageCount > 0,
            'shipping'     => $shippingKnown,
            'returns'      => $returnsKnown,
            'schema'       => $schemaPresent,
            'consistent'   => $product->price === null ? null : true,
        ];
    }

    /**
     * @return list<array{code: string, detail: string}>
     */
    public function feed( CatalogProduct $product ): array {
        $issues = [];
        if ( $product->identifier() === '' ) {
            $issues[] = [
                'code'   => 'missing_identifier',
                'detail' => 'The feed row has no GTIN, EAN, or UPC.',
            ];
        }
        if ( $product->url === '' ) {
            $issues[] = [
                'code'   => 'missing_url',
                'detail' => 'The product URL is empty.',
            ];
        }
        if ( $product->imageCount === 0 ) {
            $issues[] = [
                'code'   => 'missing_image',
                'detail' => 'The feed row has no image.',
            ];
        }
        if ( $product->price === null ) {
            $issues[] = [
                'code'   => 'missing_price',
                'detail' => 'Price was not measured.',
            ];
        }
        if ( $product->availability === '' ) {
            $issues[] = [
                'code'   => 'stock_unknown',
                'detail' => 'Stock status was not measured.',
            ];
        }
        if ( strlen( $product->description ) < 20 ) {
            $issues[] = [
                'code'   => 'short_description',
                'detail' => 'The description is under 20 characters.',
            ];
        }

        return $issues;
    }

    /**
     * @param list<array{code: string, severity: string, detail: string}> $issues
     */
    private function add( array &$issues, bool $when, string $code, string $severity, string $detail ): void {
        if ( $when ) {
            $issues[] = $this->issue( $code, $severity, $detail );
        }
    }

    /**
     * @return array{code: string, severity: string, detail: string}
     */
    private function issue( string $code, string $severity, string $detail ): array {
        return [
            'code'     => $code,
            'severity' => $severity,
            'detail'   => $detail,
        ];
    }
}
