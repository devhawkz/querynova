<?php
/**
 * Stored points for admin sparklines. A missing metric stays null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ChartSeries {

    public const LIMIT = 20;

    /**
     * Reads stored rows only. This does not call a provider or write.
     *
     * @return array{series: list<array{id: string, label: string, points: list<array{value: float|null, kind: string}>}>, fetched: false, written: false, called: false, note: string}
     */
    public static function fromDatabase( DatabaseConnection $database ): array {
        $prefix  = $database->prefix();
        $revenue = self::recent( $database, $prefix . 'qn_revenue_metrics' );

        return self::present(
            self::recent( $database, $prefix . 'qn_rank_history' ),
            self::recent( $database, $prefix . 'qn_gsc_metrics' ),
            $revenue,
            $revenue === [] ? self::recent( $database, $prefix . 'qn_commerce_metrics' ) : []
        );
    }

    /**
     * @param list<mixed> $ranks
     * @param list<mixed> $search
     * @param list<mixed> $revenue
     * @param list<mixed> $commerce
     * @return array{series: list<array{id: string, label: string, points: list<array{value: float|null, kind: string}>}>, fetched: false, written: false, called: false, note: string}
     */
    public static function present( array $ranks, array $search, array $revenue, array $commerce = [] ): array {
        $revenue_rows = array_slice( $revenue, 0, self::LIMIT );
        $measured     = $revenue_rows === []
            ? self::points( $commerce, 'revenue', 'MEASURED' )
            : self::points( $revenue_rows, 'measured_revenue', 'MEASURED' );

        return [
            'series'  => [
                self::series( 'rank', 'Rank position', self::points( $ranks, 'position', 'MEASURED' ) ),
                self::series( 'clicks', 'Clicks', self::points( $search, 'clicks', 'MEASURED' ) ),
                self::series( 'impressions', 'Impressions', self::points( $search, 'impressions', 'MEASURED' ) ),
                self::series( 'revenue-measured', 'Measured revenue', $measured ),
                self::series( 'revenue-attributed', 'Attributed revenue', $revenue_rows === [] ? [] : self::points( $revenue_rows, 'attributed_revenue', 'ATTRIBUTED' ) ),
                self::series( 'revenue-estimated', 'Estimated revenue', $revenue_rows === [] ? [] : self::points( $revenue_rows, 'estimated_revenue', 'ESTIMATED' ) ),
            ],
            'fetched' => false,
            'written' => false,
            'called'  => false,
            'note'    => 'Sparklines use stored rank, click, impression, and revenue rows. A missing point stays empty. No provider was called.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function recent( DatabaseConnection $database, string $table ): array {
        return array_reverse( $database->select( $table, [], self::LIMIT, 0, [ 'id' => 'DESC' ] ) );
    }

    /**
     * @param list<mixed> $rows
     * @return list<array{value: float|null, kind: string}>
     */
    private static function points( array $rows, string $column, string $kind ): array {
        $points = [];
        foreach ( array_slice( $rows, 0, self::LIMIT ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $value    = self::number( $row[ $column ] ?? null );
            $points[] = [
                'value' => $value,
                'kind'  => $value === null ? 'UNAVAILABLE' : $kind,
            ];
        }

        return $points;
    }

    /**
     * @param list<array{value: float|null, kind: string}> $points
     * @return array{id: string, label: string, points: list<array{value: float|null, kind: string}>}
     */
    private static function series( string $id, string $label, array $points ): array {
        return [
            'id'     => $id,
            'label'  => $label,
            'points' => $points,
        ];
    }

    private static function number( mixed $value ): ?float {
        if ( is_string( $value ) ) {
            $value = trim( $value );
            if ( $value === '' || ! is_numeric( $value ) ) {
                return null;
            }

            return (float) $value;
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            return (float) $value;
        }

        return null;
    }
}
