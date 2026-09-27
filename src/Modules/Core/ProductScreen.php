<?php
/**
 * One product screen. Missing money and counts stay empty, and estimates are not labeled measured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductScreen {

    /**
     * @var list<string>
     */
    private const TABS = [
        'overview',
        'search',
        'keywords',
        'revenue',
        'conversion',
        'content',
        'schema',
        'links',
        'competitors',
        'ai',
        'recommendations',
    ];

    /**
     * @var list<string>
     */
    private const KINDS = [ 'MEASURED', 'ATTRIBUTED', 'ESTIMATED', 'UNAVAILABLE' ];

    /**
     * @return array{title: string|null, tabs: array<string, list<array<string, mixed>>>}
     */
    public function fromDatabase( DatabaseConnection $database ): array {
        $products = $database->select( $this->table( $database, 'products' ), [], 1, 0, [ 'id' => 'DESC' ] );
        if ( $products === [] ) {
            return self::emptyScreen();
        }
        $product    = $products[0];
        $product_id = (int) ( $product['product_id'] ?? 0 );
        $metrics    = $product_id > 0 ? $database->select( $this->table( $database, 'product_metrics' ), [ 'product_id' => $product_id ], 1, 0, [ 'id' => 'DESC' ] ) : [];
        $revenue    = $product_id > 0 ? $database->select(
            $this->table( $database, 'revenue_metrics' ),
            [
				'scope'    => 'product',
				'scope_id' => $product_id,
			],
			1,
			0,
			[ 'id' => 'DESC' ]
        ) : [];
        $keyword_id = (int) ( $product['primary_keyword_id'] ?? 0 );
        $keywords   = $keyword_id > 0 ? $database->select( $this->table( $database, 'keywords' ), [ 'id' => $keyword_id ], 1 ) : [];

        return $this->compose( $product, $metrics[0] ?? null, $revenue[0] ?? null, $keywords[0] ?? null );
    }

    /**
     * @param array<string, mixed>      $product
     * @param array<string, mixed>|null $metrics
     * @param array<string, mixed>|null $revenue
     * @param array<string, mixed>|null $keyword
     * @return array{title: string|null, tabs: array<string, list<array<string, mixed>>>}
     */
    public function compose( array $product, ?array $metrics, ?array $revenue, ?array $keyword ): array {
        $tabs       = self::emptyTabs();
        $product_id = (int) ( $product['product_id'] ?? 0 );
        $sku        = trim( (string) ( $product['sku'] ?? '' ) );
        $title      = $sku !== '' ? $sku : ( $product_id > 0 ? 'Product ' . (string) $product_id : null );
        $this->overview( $tabs, $product, $sku );
        if ( is_array( $metrics ) ) {
            $this->metrics( $tabs, $metrics );
        }
        if ( is_array( $revenue ) ) {
            $this->revenue( $tabs, $revenue );
        }
        if ( is_array( $keyword ) ) {
            $name = trim( (string) ( $keyword['keyword'] ?? '' ) );
            if ( $name !== '' ) {
                $tabs['keywords'][] = $this->item( 'keyword-' . (string) ( $keyword['id'] ?? $name ), $name, '', null );
            }
        }

        return [
            'title' => $title,
            'tabs'  => $tabs,
        ];
    }

    /**
     * @return array{title: null, tabs: array<string, list<array<string, mixed>>>}
     */
    public static function emptyScreen(): array {
        return [
            'title' => null,
            'tabs'  => self::emptyTabs(),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function emptyTabs(): array {
        $tabs = [];
        foreach ( self::TABS as $tab ) {
            $tabs[ $tab ] = [];
        }

        return $tabs;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tabs
     * @param array<string, mixed>                      $product
     */
    private function overview( array &$tabs, array $product, string $sku ): void {
        if ( $sku !== '' ) {
            $tabs['overview'][] = $this->item( 'sku', 'SKU', $sku, null );
        }
        $brand = trim( (string) ( $product['brand'] ?? '' ) );
        if ( $brand !== '' ) {
            $tabs['overview'][] = $this->item( 'brand', 'Brand', $brand, null );
        }
        $availability = trim( (string) ( $product['availability'] ?? '' ) );
        if ( $availability !== '' ) {
            $tabs['overview'][] = $this->item( 'availability', 'Availability', $availability, null );
        }
        $indexability = trim( (string) ( $product['indexability'] ?? '' ) );
        if ( $indexability !== '' && $indexability !== 'unknown' ) {
            $tabs['overview'][] = $this->item( 'indexability', 'Indexability', $indexability, null );
        }
        $price = $this->amount( $product['price'] ?? null );
        if ( $price !== null ) {
            $currency           = trim( (string) ( $product['currency'] ?? '' ) );
            $tabs['overview'][] = $this->item( 'price', 'Price', trim( $price . ' ' . $currency ) . ' · Measured', null );
        }
    }

    /**
     * Money is shown only when a provenance label was stored with it.
     *
     * @param array<string, list<array<string, mixed>>> $tabs
     * @param array<string, mixed>                      $metrics
     */
    private function metrics( array &$tabs, array $metrics ): void {
        $provenance = $this->provenance( $metrics['provenance_json'] ?? null );
        $this->observation( $tabs, 'search', 'impressions', 'Impressions', $metrics['impressions'] ?? null, $provenance['impressions'] ?? 'MEASURED' );
        $this->observation( $tabs, 'search', 'clicks', 'Clicks', $metrics['clicks'] ?? null, $provenance['clicks'] ?? 'MEASURED' );
        $this->observation( $tabs, 'search', 'position', 'Position', $metrics['position'] ?? null, $provenance['position'] ?? 'MEASURED' );
        $this->observation( $tabs, 'conversion', 'cvr', 'Conversion rate', $metrics['cvr'] ?? null, $provenance['cvr'] ?? 'MEASURED' );
        if ( isset( $provenance['revenue'] ) ) {
            $this->observation( $tabs, 'revenue', 'product-revenue', 'Revenue', $metrics['revenue'] ?? null, $provenance['revenue'] );
        }
        $referrals = $this->amount( $metrics['ai_referrals'] ?? null );
        if ( $referrals !== null ) {
            $tabs['ai'][] = $this->item( 'ai-referrals', 'AI referrals', $referrals . ' · Measured. Not an official provider ranking.', null );
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tabs
     * @param array<string, mixed>                      $revenue
     */
    private function revenue( array &$tabs, array $revenue ): void {
        $this->money( $tabs, 'measured', 'Measured revenue', $revenue['measured_revenue'] ?? null, 'MEASURED' );
        $this->money( $tabs, 'attributed', 'Attributed revenue', $revenue['attributed_revenue'] ?? null, 'ATTRIBUTED' );
        $this->money( $tabs, 'estimated', 'Estimated revenue', $revenue['estimated_revenue'] ?? null, 'ESTIMATED' );
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tabs
     */
    private function observation( array &$tabs, string $tab, string $id, string $title, mixed $value, string $kind ): void {
        $amount = $this->amount( $value );
        if ( $amount === null || ! in_array( $kind, self::KINDS, true ) || $kind === 'UNAVAILABLE' ) {
            return;
        }
        $tabs[ $tab ][] = $this->item( $id, $title, $amount . ' · ' . $this->label( $kind ), null );
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tabs
     */
    private function money( array &$tabs, string $id, string $title, mixed $value, string $kind ): void {
        $amount = $this->amount( $value );
        if ( $amount === null ) {
            return;
        }
        $tabs['revenue'][] = $this->item( 'revenue-' . $id, $title, $amount . ' · ' . $this->label( $kind ), null );
    }

    /**
     * @return array<string, string>
     */
    private function provenance( mixed $value ): array {
        if ( is_string( $value ) && $value !== '' ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : [];
        }
        if ( ! is_array( $value ) ) {
            return [];
        }
        $kinds = [];
        foreach ( $value as $key => $kind ) {
            if ( is_string( $key ) && is_string( $kind ) && in_array( $kind, self::KINDS, true ) ) {
                $kinds[ $key ] = $kind;
            }
        }

        return $kinds;
    }

    private function label( string $kind ): string {
        return match ( $kind ) {
            'MEASURED' => 'Measured',
            'ATTRIBUTED' => 'Attributed',
            'ESTIMATED' => 'Estimated',
            default => 'Unavailable',
        };
    }

    private function amount( mixed $value ): ?string {
        if ( is_string( $value ) ) {
            $trimmed = trim( $value );

            return $trimmed !== '' && is_numeric( $trimmed ) ? $trimmed : null;
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            $formatted = rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );

            return $formatted === '' ? '0' : $formatted;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $metric
     * @return array<string, mixed>
     */
    private function item( string $id, string $title, string $summary, ?array $metric ): array {
        return [
            'id'      => $id,
            'title'   => $title,
            'summary' => $summary,
            'metric'  => $metric,
        ];
    }

    private function table( DatabaseConnection $database, string $name ): string {
        return $database->prefix() . 'qn_' . $name;
    }
}
