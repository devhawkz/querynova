<?php
/**
 * Search, traffic, commerce, and diagnosis from supplied rows.
 *
 * Missing numbers stay null. A query is not assigned to an order unless a join was supplied.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Application;

use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Core\Domain\ProvenanceKind;
use QueryNova\Modules\Analytics\Domain\SearchConsoleRow;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AnalyticsWorkspace {

    /**
     * @param list<SearchConsoleRow>|null $rows
     * @return array<string, mixed>
     */
    public function search( ?array $rows ): array {
        if ( $rows === null ) {
            return $this->unavailableSearch( 'Search Console is not connected.' );
        }
        if ( $rows === [] ) {
            return [
                'status'      => ProvenanceKind::Measured->value,
                'clicks'      => 0,
                'impressions' => 0,
                'ctr'         => null,
                'position'    => null,
                'note'        => 'The provider returned no rows. CTR and position are undefined, not zero.',
            ];
        }
        $clicks          = 0;
        $impressions     = 0;
        $weighted        = 0.0;
        $weight          = 0;
        $positionMissing = false;
        foreach ( $rows as $row ) {
            if ( $row->clicks === null || $row->impressions === null ) {
                return $this->unavailableSearch( 'A row omitted clicks or impressions, so the total is unavailable.' );
            }
            $clicks      += $row->clicks;
            $impressions += $row->impressions;
            if ( $row->impressions > 0 && $row->position === null ) {
                $positionMissing = true;
                continue;
            }
            if ( $row->position !== null && $row->impressions > 0 ) {
                $weighted += $row->position * $row->impressions;
                $weight   += $row->impressions;
            }
        }

        return [
            'status'      => ProvenanceKind::Measured->value,
            'clicks'      => $clicks,
            'impressions' => $impressions,
            'ctr'         => $impressions > 0 ? round( $clicks / $impressions, 6 ) : null,
            'position'    => ( $positionMissing || $weight === 0 ) ? null : round( $weighted / $weight, 2 ),
            'note'        => 'CTR is derived from measured clicks and impressions. Position stays null when any impressed row omitted it.',
        ];
    }

    /**
     * @param list<SearchConsoleRow>|null $current
     * @param list<SearchConsoleRow>|null $previous
     * @return array<string, mixed>
     */
    public function movement( ?array $current, ?array $previous ): array {
        if ( $current === null || $previous === null ) {
            return [
                'status'            => ProvenanceKind::Unavailable->value,
                'growing_queries'   => null,
                'declining_queries' => null,
                'ranking_gains'     => null,
                'ranking_losses'    => null,
                'note'              => 'Movement needs both periods. A missing period is not zero.',
            ];
        }
        $now       = $this->byQuery( $current );
        $then      = $this->byQuery( $previous );
        $growing   = [];
        $declining = [];
        $gains     = [];
        $losses    = [];
        foreach ( $now as $query => $row ) {
            $before = $then[ $query ]['clicks'] ?? 0;
            if ( $row['clicks'] > $before ) {
                $growing[] = $query;
            }
            $past = $then[ $query ]['position'] ?? null;
            if ( $row['position'] !== null && $past !== null && $row['position'] < $past ) {
                $gains[] = $query;
            }
            if ( $row['position'] !== null && $past !== null && $row['position'] > $past ) {
                $losses[] = $query;
            }
        }
        foreach ( $then as $query => $row ) {
            $after = $now[ $query ]['clicks'] ?? 0;
            if ( $row['clicks'] > $after ) {
                $declining[] = $query;
            }
        }

        return [
            'status'            => ProvenanceKind::Measured->value,
            'growing_queries'   => $growing,
            'declining_queries' => $declining,
            'ranking_gains'     => $gains,
            'ranking_losses'    => $losses,
            'note'              => 'A query with a missing position is left out of ranking gains and losses.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview( ?int $clicks, ?int $sessions, ?float $revenue, ?int $orders, ?int $top3, ?int $top10, ?int $aiSessions, ?float $aiRevenue ): array {
        return [
            'organic_clicks'      => $this->metric( $clicks === null ? null : (float) $clicks, ProvenanceKind::Measured ),
            'organic_sessions'    => $this->metric( $sessions === null ? null : (float) $sessions, ProvenanceKind::Measured ),
            'organic_revenue'     => $this->metric( $revenue, ProvenanceKind::Measured ),
            'organic_orders'      => $this->metric( $orders === null ? null : (float) $orders, ProvenanceKind::Measured ),
            'organic_cvr'         => $this->ratio( $orders === null ? null : (float) $orders, $sessions === null ? null : (float) $sessions ),
            'average_order_value' => $this->ratio( $revenue, $orders === null ? null : (float) $orders ),
            'revenue_per_session' => $this->ratio( $revenue, $sessions === null ? null : (float) $sessions ),
            'top_3_keywords'      => $this->metric( $top3 === null ? null : (float) $top3, ProvenanceKind::Measured ),
            'top_10_keywords'     => $this->metric( $top10 === null ? null : (float) $top10, ProvenanceKind::Measured ),
            'ai_sessions'         => $this->metric( $aiSessions === null ? null : (float) $aiSessions, ProvenanceKind::Measured ),
            'ai_revenue'          => $this->metric( $aiRevenue, ProvenanceKind::Measured ),
        ];
    }

    /**
     * Query revenue is attributed only when a join and a methodology were supplied.
     *
     * @return array<string, mixed>
     */
    public function queryRevenue( ?float $revenue, bool $queryJoinedToOrder, string $methodology ): array {
        if ( $revenue === null || ! $queryJoinedToOrder || trim( $methodology ) === '' ) {
            return [
                'value'       => null,
                'provenance'  => ProvenanceKind::Unavailable->value,
                'confidence'  => ConfidenceBand::Unknown->value,
                'methodology' => null,
                'note'        => 'A hidden search query is not assigned to an order.',
            ];
        }

        return [
            'value'       => $revenue,
            'provenance'  => ProvenanceKind::Attributed->value,
            'confidence'  => ConfidenceBand::Medium->value,
            'methodology' => $methodology,
            'note'        => 'Attributed because a query-to-order join and a methodology were supplied. This is not a measured Google query on the order.',
        ];
    }

    /**
     * @param array<string, int|float|null> $fields
     * @return array<string, int|float|null>
     */
    public function product( array $fields ): array {
        $keys = [ 'impressions', 'clicks', 'position', 'organic_sessions', 'views', 'add_to_cart', 'orders', 'cvr', 'revenue', 'refund_adjusted_revenue', 'margin', 'profit', 'ai_referrals', 'opportunity' ];
        $row  = [];
        foreach ( $keys as $key ) {
            $row[ $key ] = $fields[ $key ] ?? null;
        }
        if ( ! array_key_exists( 'opportunity', $fields ) ) {
            $row['opportunity'] = null;
        }

        return $row;
    }

    /**
     * @param array<string, int|float|null> $fields
     * @return array<string, int|float|null>
     */
    public function category( array $fields ): array {
        $row = [];
        foreach ( [ 'impressions', 'clicks', 'ctr', 'position', 'sessions', 'orders', 'revenue', 'cvr', 'aov', 'margin', 'keywords', 'opportunity' ] as $key ) {
            $row[ $key ] = $fields[ $key ] ?? null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function profit( ?float $revenue, ?float $cost ): array {
        if ( $revenue === null || $cost === null ) {
            return [
                'status' => ProvenanceKind::Unavailable->value,
                'profit' => null,
                'margin' => null,
                'note'   => 'Cost was not supplied, so profit is unavailable.',
            ];
        }
        $profit = round( $revenue - $cost, 4 );

        return [
            'status' => ProvenanceKind::Measured->value,
            'profit' => $profit,
            'margin' => $revenue > 0 ? round( $profit / $revenue, 6 ) : null,
            'note'   => 'Profit is revenue minus the supplied cost.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inventory( ?string $stock ): array {
        if ( $stock === null || $stock === '' ) {
            return [
                'status'       => ProvenanceKind::Unavailable->value,
                'push_traffic' => null,
                'applied'      => false,
                'note'         => 'Stock is unknown, so no traffic decision is made.',
            ];
        }
        if ( $stock === 'out_of_stock' ) {
            return [
                'status'       => ProvenanceKind::Measured->value,
                'push_traffic' => false,
                'applied'      => false,
                'note'         => 'Do not push traffic to an unavailable product unless an alternative is supplied. Nothing was changed.',
            ];
        }

        return [
            'status'       => ProvenanceKind::Measured->value,
            'push_traffic' => $stock === 'in_stock',
            'applied'      => false,
            'note'         => 'Stock is recorded. No index or content change was applied.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function seasonality( ?float $current, ?float $previousYear ): array {
        if ( $current === null || $previousYear === null ) {
            return [
                'status' => ProvenanceKind::Unavailable->value,
                'delta'  => null,
                'ratio'  => null,
                'note'   => 'Year-over-year needs both periods.',
            ];
        }

        return [
            'status' => ProvenanceKind::Measured->value,
            'delta'  => round( $current - $previousYear, 4 ),
            'ratio'  => $previousYear > 0 ? round( $current / $previousYear, 6 ) : null,
            'note'   => 'Compared with the supplied prior-year value.',
        ];
    }

    /**
     * @param array<string, int|null> $stages
     * @return array<string, mixed>
     */
    public function funnel( array $stages ): array {
        $names = [ 'landing', 'product_view', 'add_to_cart', 'checkout', 'purchase' ];
        $steps = [];
        foreach ( $names as $name ) {
            $steps[ $name ] = array_key_exists( $name, $stages ) ? $stages[ $name ] : null;
        }
        $drop       = [];
        $stageCount = count( $names );
        for ( $index = 1; $index < $stageCount; $index++ ) {
            $from = $steps[ $names[ $index - 1 ] ];
            $to   = $steps[ $names[ $index ] ];
            if ( ! is_int( $from ) || ! is_int( $to ) || $from <= 0 || $to > $from ) {
                $drop[ $names[ $index ] ] = null;
                continue;
            }
            $drop[ $names[ $index ] ] = round( ( $from - $to ) / $from, 6 );
        }

        return [
            'steps'   => $steps,
            'dropoff' => $drop,
            'note'    => 'Drop-off is calculated only when both stages are measured and the later stage does not exceed the earlier one.',
        ];
    }

    public function ctrIsWeak( ?float $ctr, ?float $expected ): ?bool {
        if ( $ctr === null || $expected === null ) {
            return null;
        }

        return $ctr < $expected;
    }

    /**
     * @param array<string, bool|string|null> $signals
     * @return array<string, mixed>
     */
    public function diagnose( array $signals ): array {
        $stock = $signals['stock'] ?? null;
        if ( $stock === 'out_of_stock' ) {
            return $this->problem( 'stock', 'The product is out of stock. Search changes are not the first action.' );
        }
        if ( ( $signals['offer_weak'] ?? null ) === true ) {
            return $this->problem( 'offer', 'The offer was marked weak from supplied evidence.' );
        }
        if ( ( $signals['intent_mismatch'] ?? null ) === true ) {
            return $this->problem( 'intent', 'The page type does not match the supplied intent.' );
        }
        if ( ( $signals['conversion_weak'] ?? null ) === true && ( $signals['visibility_weak'] ?? null ) !== true ) {
            return $this->problem( 'conversion', 'Visibility is not the weak signal. Conversion was marked weak from supplied evidence.' );
        }
        if ( ( $signals['ctr_weak'] ?? null ) === true ) {
            return $this->problem( 'ctr', 'CTR is below the supplied expected CTR. This is a title or snippet candidate, not a rank score.' );
        }
        if ( ( $signals['visibility_weak'] ?? null ) === true ) {
            return $this->problem( 'visibility', 'The supplied position is outside the visible set used for this check.' );
        }
        if ( ( $signals['content_gap'] ?? null ) === true ) {
            return $this->problem( 'content', 'A content gap was supplied.' );
        }
        if ( ( $signals['authority_gap'] ?? null ) === true ) {
            return $this->problem( 'authority', 'An authority gap was supplied. Authority was not calculated here.' );
        }
        $any = false;
        foreach ( $signals as $value ) {
            if ( $value !== null ) {
                $any = true;
            }
        }
        if ( ! $any ) {
            return [
                'status'  => ProvenanceKind::Unavailable->value,
                'problem' => null,
                'note'    => 'No diagnostic signals were supplied.',
            ];
        }

        return [
            'status'  => ProvenanceKind::Measured->value,
            'problem' => null,
            'note'    => 'The supplied signals do not identify a single problem.',
        ];
    }

    /**
     * @param list<SearchConsoleRow> $rows
     * @return array<string, array{clicks: int, position: float|null}>
     */
    private function byQuery( array $rows ): array {
        $grouped = [];
        foreach ( $rows as $row ) {
            if ( $row->clicks === null ) {
                continue;
            }
            if ( ! isset( $grouped[ $row->query ] ) ) {
                $grouped[ $row->query ] = [
                    'clicks'   => 0,
                    'position' => $row->position,
                    'weight'   => 0,
                    'weighted' => 0.0,
                ];
            }
            $grouped[ $row->query ]['clicks'] += $row->clicks;
            if ( $row->position !== null && $row->impressions !== null && $row->impressions > 0 ) {
                $grouped[ $row->query ]['weighted'] += $row->position * $row->impressions;
                $grouped[ $row->query ]['weight']   += $row->impressions;
            }
        }
        $clean = [];
        foreach ( $grouped as $query => $row ) {
            $clean[ $query ] = [
                'clicks'   => $row['clicks'],
                'position' => $row['weight'] > 0 ? $row['weighted'] / $row['weight'] : null,
            ];
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableSearch( string $note ): array {
        return [
            'status'      => ProvenanceKind::Unavailable->value,
            'clicks'      => null,
            'impressions' => null,
            'ctr'         => null,
            'position'    => null,
            'note'        => $note,
        ];
    }

    /**
     * @return array{value: float|null, provenance: string}
     */
    private function metric( ?float $value, ProvenanceKind $kind ): array {
        if ( $value === null ) {
            return [
                'value'      => null,
                'provenance' => ProvenanceKind::Unavailable->value,
            ];
        }

        return [
            'value'      => $value,
            'provenance' => $kind->value,
        ];
    }

    /**
     * @return array{value: float|null, provenance: string}
     */
    private function ratio( ?float $numerator, ?float $denominator ): array {
        if ( $numerator === null || $denominator === null || $denominator <= 0 ) {
            return [
                'value'      => null,
                'provenance' => ProvenanceKind::Unavailable->value,
            ];
        }

        return [
            'value'      => round( $numerator / $denominator, 6 ),
            'provenance' => ProvenanceKind::Measured->value,
        ];
    }

    /**
     * @return array{status: string, problem: string, note: string}
     */
    private function problem( string $problem, string $note ): array {
        return [
            'status'  => ProvenanceKind::Measured->value,
            'problem' => $problem,
            'note'    => $note,
        ];
    }
}
