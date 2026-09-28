<?php
/**
 * One category screen. A default product count of zero is not shown as a measurement.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Commerce\Application\CatalogWorkspace;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CategoryScreen {

    /**
     * @var list<string>
     */
    private const TABS = [
        'overview',
        'keywords',
        'revenue',
        'products',
        'content',
        'serp',
        'filters',
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
     * @return array{title: string|null, tabs: array<string, list<array<string, mixed>>>, workspace: array<string, mixed>}
     */
    public function fromDatabase( DatabaseConnection $database ): array {
        $workspace  = CatalogWorkspace::present( $this->categoryRows( $database ), '', '', '', 'category' );
        $categories = $database->select( $this->table( $database, 'categories' ), [], 1, 0, [ 'id' => 'DESC' ] );
        if ( $categories === [] ) {
            $empty              = self::emptyScreen();
            $empty['workspace'] = $workspace;

            return $empty;
        }
        $category    = $categories[0];
        $category_id = (int) ( $category['term_id'] ?? 0 );
        $metrics     = $category_id > 0 ? $database->select( $this->table( $database, 'category_metrics' ), [ 'category_id' => $category_id ], 1, 0, [ 'id' => 'DESC' ] ) : [];
        $revenue     = $category_id > 0 ? $database->select(
            $this->table( $database, 'revenue_metrics' ),
            [
				'scope'    => 'category',
				'scope_id' => $category_id,
			],
			1,
			0,
			[ 'id' => 'DESC' ]
        ) : [];
        $keyword_id  = (int) ( $category['primary_keyword_id'] ?? 0 );
        $keywords    = $keyword_id > 0 ? $database->select( $this->table( $database, 'keywords' ), [ 'id' => $keyword_id ], 1 ) : [];

        $screen              = $this->compose( $category, $metrics[0] ?? null, $revenue[0] ?? null, $keywords[0] ?? null );
        $screen['workspace'] = $workspace;

        return $screen;
    }

    /**
     * @param array<string, mixed>      $category
     * @param array<string, mixed>|null $metrics
     * @param array<string, mixed>|null $revenue
     * @param array<string, mixed>|null $keyword
     * @return array{title: string|null, tabs: array<string, list<array<string, mixed>>>, workspace: array<string, mixed>}
     */
    public function compose( array $category, ?array $metrics, ?array $revenue, ?array $keyword ): array {
        $tabs        = self::emptyTabs();
        $category_id = (int) ( $category['term_id'] ?? 0 );
        $title       = $category_id > 0 ? sprintf(
            /* translators: %s: category id. */
            __( 'Category %s', 'querynova' ),
            (string) $category_id
        ) : null;
        $count = (int) ( $category['product_count'] ?? 0 );
        if ( $count > 0 ) {
            $tabs['products'][] = $this->item( 'products', __( 'Products', 'querynova' ), (string) $count . ' · ' . __( 'Measured', 'querynova' ), null );
        }
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
            'title'     => $title,
            'tabs'      => $tabs,
            'workspace' => CatalogWorkspace::present( [], '', '', '', 'category' ),
        ];
    }

    /**
     * @return array{title: null, tabs: array<string, list<array<string, mixed>>>, workspace: array<string, mixed>}
     */
    public static function emptyScreen(): array {
        return [
            'title'     => null,
            'tabs'      => self::emptyTabs(),
            'workspace' => CatalogWorkspace::present( [], '', '', '', 'category' ),
        ];
    }

    /**
     * @return list<array{id: int, name: string, sku: string, issues: list<string>, opportunity: string}>
     */
    private function categoryRows( DatabaseConnection $database ): array {
        $rows = [];
        foreach ( $database->select( $this->table( $database, 'categories' ), [], 20, 0, [ 'id' => 'DESC' ] ) as $category ) {
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
     * @param array<string, mixed>                      $metrics
     */
    private function metrics( array &$tabs, array $metrics ): void {
        $provenance = $this->provenance( $metrics['provenance_json'] ?? null );
        $this->observation( $tabs, 'serp', 'impressions', __( 'Impressions', 'querynova' ), $metrics['impressions'] ?? null, $provenance['impressions'] ?? 'MEASURED' );
        $this->observation( $tabs, 'serp', 'clicks', __( 'Clicks', 'querynova' ), $metrics['clicks'] ?? null, $provenance['clicks'] ?? 'MEASURED' );
        $this->observation( $tabs, 'serp', 'position', __( 'Position', 'querynova' ), $metrics['position'] ?? null, $provenance['position'] ?? 'MEASURED' );
        if ( isset( $provenance['revenue'] ) ) {
            $this->observation( $tabs, 'revenue', 'category-revenue', __( 'Revenue', 'querynova' ), $metrics['revenue'] ?? null, $provenance['revenue'] );
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tabs
     * @param array<string, mixed>                      $revenue
     */
    private function revenue( array &$tabs, array $revenue ): void {
        $this->money( $tabs, 'measured', __( 'Measured revenue', 'querynova' ), $revenue['measured_revenue'] ?? null, 'MEASURED' );
        $this->money( $tabs, 'attributed', __( 'Attributed revenue', 'querynova' ), $revenue['attributed_revenue'] ?? null, 'ATTRIBUTED' );
        $this->money( $tabs, 'estimated', __( 'Estimated revenue', 'querynova' ), $revenue['estimated_revenue'] ?? null, 'ESTIMATED' );
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
            'MEASURED' => __( 'Measured', 'querynova' ),
            'ATTRIBUTED' => __( 'Attributed', 'querynova' ),
            'ESTIMATED' => __( 'Estimated', 'querynova' ),
            default => __( 'Unavailable', 'querynova' ),
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
