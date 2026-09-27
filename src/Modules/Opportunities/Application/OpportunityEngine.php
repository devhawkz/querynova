<?php
/**
 * Opportunity notes from supplied inputs. There is no 0–100 score.
 *
 * Incremental revenue is estimated only from supplied clicks, impressions, CTR, and revenue.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Opportunities\Application;

use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Core\Domain\ProvenanceKind;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OpportunityEngine {

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function evaluate( array $input ): array {
        $inventory = $this->string( $input, 'inventory' );
        if ( $inventory === 'out_of_stock' ) {
            return $this->blocked( 'The product is out of stock, so traffic growth is not recommended. Nothing was changed.' );
        }
        if ( ! $this->hasSignal( $input ) ) {
            return [
                'status'              => ProvenanceKind::Unavailable->value,
                'opportunity'         => null,
                'impact'              => $this->money( null, ProvenanceKind::Unavailable, null, 'Impact is unavailable.' ),
                'incremental_revenue' => $this->money( null, ProvenanceKind::Unavailable, null, 'Revenue was not supplied.' ),
                'incremental_profit'  => $this->money( null, ProvenanceKind::Unavailable, null, 'Profit was not supplied.' ),
                'confidence'          => ConfidenceBand::Unknown->value,
                'priority'            => null,
                'recommendations'     => [],
                'applied'             => false,
                'note'                => 'Position, impressions, revenue, and gap flags were not supplied, so there is no opportunity.',
            ];
        }
        $code       = $this->code( $input );
        $revenue    = $this->incrementalRevenue( $input );
        $profit     = $this->incrementalProfit( $revenue['value'], $this->float( $input, 'margin' ) );
        $priority   = $this->priority( $this->string( $input, 'business_value' ), $code );
        $confidence = $revenue['value'] !== null ? ConfidenceBand::Low->value : $this->confidence( $input );
        $status     = $revenue['value'] !== null ? ProvenanceKind::Estimated : ProvenanceKind::Measured;

        return [
            'status'              => $status->value,
            'opportunity'         => $code,
            'impact'              => $revenue,
            'incremental_revenue' => $revenue,
            'incremental_profit'  => $profit,
            'confidence'          => $confidence,
            'priority'            => $priority,
            'recommendations'     => $this->recommendations( $input, $code, $priority, $confidence, $revenue ),
            'applied'             => false,
            'note'                => 'QueryNova does not calculate a 0-100 opportunity score. Incremental money is estimated only when the CTR gap inputs exist.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function decay( array $input ): array {
        $classes = [];
        if ( $this->fell( $input, 'clicks' ) && $this->fell( $input, 'impressions' ) ) {
            $classes[] = 'demand_decline';
        }
        if ( $this->rankWorsened( $input ) && ! $this->fell( $input, 'impressions' ) ) {
            $classes[] = 'ranking_decline';
        }
        if ( $this->fell( $input, 'ctr' ) && $this->rankStableOrBetter( $input ) ) {
            $classes[] = 'ctr_decline';
        }
        if ( ( $input['seasonality'] ?? null ) === true ) {
            $classes[] = 'seasonality';
        }
        if ( ( $input['competition'] ?? null ) === true ) {
            $classes[] = 'competition';
        }
        if ( ( $input['stock'] ?? null ) === 'out_of_stock' ) {
            $classes[] = 'stock';
        }
        if ( ( $input['offer'] ?? null ) === true ) {
            $classes[] = 'offer';
        }
        if ( $classes === [] ) {
            return [
                'status'         => ProvenanceKind::Unavailable->value,
                'classification' => null,
                'note'           => 'Decay is unavailable until both periods, or an explicit cause, are supplied.',
            ];
        }

        return [
            'status'         => ProvenanceKind::Measured->value,
            'classification' => $classes,
            'note'           => 'Only causes with supplied evidence are listed.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function ctr( array $input ): array {
        $high     = $input['impressions_are_high'] ?? null;
        $position = $input['position_is_good'] ?? null;
        $weak     = $input['ctr_is_weak'] ?? null;
        if ( ! is_bool( $high ) || ! is_bool( $position ) || ! is_bool( $weak ) ) {
            return [
                'status'      => ProvenanceKind::Unavailable->value,
                'opportunity' => null,
                'applied'     => false,
                'note'        => 'CTR intelligence needs supplied flags for impressions, position, and relative CTR. A threshold was not assumed.',
            ];
        }
        if ( $high && $position && $weak ) {
            return [
                'status'      => ProvenanceKind::Estimated->value,
                'opportunity' => 'title_snippet',
                'applied'     => false,
                'note'        => 'High impressions, a good supplied position, and a weak relative CTR suggest a title or snippet test.',
            ];
        }

        return [
            'status'      => ProvenanceKind::Measured->value,
            'opportunity' => null,
            'applied'     => false,
            'note'        => 'The supplied CTR flags do not show a title or snippet opportunity.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function hasSignal( array $input ): bool {
        foreach ( [ 'position', 'impressions', 'revenue', 'content_gap', 'authority_gap', 'backlink_gap', 'internal_links' ] as $key ) {
            if ( array_key_exists( $key, $input ) && $input[ $key ] !== null ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function code( array $input ): ?string {
        $ctr = $this->ctr( $input );
        if ( $ctr['opportunity'] === 'title_snippet' ) {
            return 'title_snippet';
        }
        $position    = $this->float( $input, 'position' );
        $impressions = $this->float( $input, 'impressions' );
        if ( $position !== null && $impressions !== null && $position > 10 ) {
            return 'ranking';
        }
        if ( ( $input['content_gap'] ?? null ) === true ) {
            return 'content';
        }
        if ( ( $input['intent_match'] ?? null ) === false ) {
            return 'intent';
        }
        $links = $input['internal_links'] ?? null;
        if ( is_int( $links ) && $links === 0 && $position !== null && $position >= 4 && $position <= 20 ) {
            return 'internal_links';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{value: float|null, provenance: string, methodology: string|null, note: string}
     */
    private function incrementalRevenue( array $input ): array {
        $impressions = $this->float( $input, 'impressions' );
        $ctr         = $this->float( $input, 'ctr' );
        $expected    = $this->float( $input, 'expected_ctr' );
        $clicks      = $this->float( $input, 'clicks' );
        $revenue     = $this->float( $input, 'revenue' );
        if ( $impressions === null || $ctr === null || $expected === null || $clicks === null || $revenue === null || $clicks <= 0 || $expected <= $ctr ) {
            return $this->money( null, ProvenanceKind::Unavailable, null, 'Incremental revenue stays unavailable until impressions, CTR, expected CTR, clicks, and revenue are all supplied.' );
        }
        $extra = $impressions * ( $expected - $ctr );

        return $this->money(
            round( $extra * ( $revenue / $clicks ), 4 ),
            ProvenanceKind::Estimated,
            'querynova.ctr_revenue_gap',
            'Estimated extra clicks at the supplied CTR gap, valued at the current revenue per click. This is not measured revenue.'
        );
    }

    /**
     * @return array{value: float|null, provenance: string, methodology: string|null, note: string}
     */
    private function incrementalProfit( ?float $revenue, ?float $margin ): array {
        if ( $revenue === null || $margin === null ) {
            return $this->money( null, ProvenanceKind::Unavailable, null, 'Profit stays unavailable until estimated revenue and a margin ratio are supplied.' );
        }
        if ( $margin < 0 || $margin > 1 ) {
            return $this->money( null, ProvenanceKind::Unavailable, null, 'Margin must be a ratio from 0 to 1. A percent was not assumed.' );
        }

        return $this->money( round( $revenue * $margin, 4 ), ProvenanceKind::Estimated, 'querynova.ctr_revenue_gap', 'Estimated revenue multiplied by the supplied margin ratio.' );
    }

    private function priority( ?string $value, ?string $code ): ?string {
        if ( $code === null || ! in_array( $value, [ 'low', 'medium', 'high', 'critical' ], true ) ) {
            return null;
        }
        if ( $value === 'critical' || $value === 'high' ) {
            return 'high';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function confidence( array $input ): string {
        if ( $this->float( $input, 'position' ) !== null && $this->float( $input, 'impressions' ) !== null && $this->float( $input, 'revenue' ) !== null ) {
            return ConfidenceBand::Medium->value;
        }

        return ConfidenceBand::Low->value;
    }

    /**
     * @param array<string, mixed>                                    $input
     * @param array{value: float|null, provenance: string, methodology: string|null, note: string} $impact
     * @return list<array<string, mixed>>
     */
    private function recommendations( array $input, ?string $code, ?string $priority, string $confidence, array $impact ): array {
        if ( $code === null || $priority === null ) {
            return [];
        }
        $query = $this->string( $input, 'query' ) ?? '';
        $url   = $this->string( $input, 'url' );
        $title = match ( $code ) {
            'title_snippet' => 'Test the title and snippet',
            'ranking' => 'Improve the page that ranks beyond the first page',
            'content' => 'Close the supplied content gap',
            'intent' => 'Align the page with the supplied intent',
            'internal_links' => 'Add internal links to this URL',
            default => 'Review the supplied opportunity',
        };

        return [
            [
                'title'        => $title,
                'description'  => 'Work from the supplied evidence. QueryNova did not apply a change.',
                'url'          => $url,
                'target_query' => $query,
                'impact'       => $impact['provenance'] === ProvenanceKind::Estimated->value ? 'estimated' : 'unavailable',
                'confidence'   => $confidence,
                'effort'       => $this->effort( $input ),
                'evidence'     => $this->evidence( $input ),
                'data_sources' => $this->sources( $input ),
                'expected_kpi' => $code === 'title_snippet' ? 'ctr' : 'qualified_visits',
                'rationale'    => 'Business value was ' . $this->string( $input, 'business_value' ) . '.',
                'priority'     => $priority,
                'applied'      => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function evidence( array $input ): array {
        $rows = [];
        foreach ( [ 'position', 'impressions', 'ctr', 'expected_ctr', 'clicks', 'revenue', 'content_gap', 'internal_links' ] as $key ) {
            if ( array_key_exists( $key, $input ) && $input[ $key ] !== null ) {
                $rows[] = $key;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function sources( array $input ): array {
        $sources = [];
        if ( $this->float( $input, 'impressions' ) !== null ) {
            $sources[] = 'search';
        }
        if ( $this->float( $input, 'revenue' ) !== null ) {
            $sources[] = 'commerce';
        }
        if ( ( $input['content_gap'] ?? null ) !== null ) {
            $sources[] = 'content';
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function effort( array $input ): string {
        $effort = $this->string( $input, 'effort' );
        if ( ! in_array( $effort, [ 'low', 'medium', 'high' ], true ) ) {
            return 'unknown';
        }

        return $effort;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function fell( array $input, string $metric ): bool {
        $current  = $input[ 'current_' . $metric ] ?? null;
        $previous = $input[ 'previous_' . $metric ] ?? null;
        if ( ! is_numeric( $current ) || ! is_numeric( $previous ) ) {
            return false;
        }

        return (float) $current < (float) $previous;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function rankWorsened( array $input ): bool {
        $current  = $input['current_rank'] ?? null;
        $previous = $input['previous_rank'] ?? null;
        if ( ! is_numeric( $current ) || ! is_numeric( $previous ) ) {
            return false;
        }

        return (float) $current > (float) $previous;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function rankStableOrBetter( array $input ): bool {
        $current  = $input['current_rank'] ?? null;
        $previous = $input['previous_rank'] ?? null;
        if ( ! is_numeric( $current ) || ! is_numeric( $previous ) ) {
            return false;
        }

        return (float) $current <= (float) $previous;
    }

    /**
     * @return array<string, mixed>
     */
    private function blocked( string $note ): array {
        return [
            'status'              => ProvenanceKind::Measured->value,
            'opportunity'         => null,
            'impact'              => $this->money( null, ProvenanceKind::Unavailable, null, $note ),
            'incremental_revenue' => $this->money( null, ProvenanceKind::Unavailable, null, $note ),
            'incremental_profit'  => $this->money( null, ProvenanceKind::Unavailable, null, $note ),
            'confidence'          => ConfidenceBand::Unknown->value,
            'priority'            => null,
            'recommendations'     => [],
            'applied'             => false,
            'note'                => $note,
        ];
    }

    /**
     * @return array{value: float|null, provenance: string, methodology: string|null, note: string}
     */
    private function money( ?float $value, ProvenanceKind $kind, ?string $methodology, string $note ): array {
        return [
            'value'       => $value,
            'provenance'  => $kind->value,
            'methodology' => $value === null ? null : $methodology,
            'note'        => $note,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function float( array $input, string $key ): ?float {
        $value = $input[ $key ] ?? null;
        if ( ! is_int( $value ) && ! is_float( $value ) ) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function string( array $input, string $key ): ?string {
        $value = $input[ $key ] ?? null;
        if ( ! is_string( $value ) || trim( $value ) === '' ) {
            return null;
        }

        return trim( $value );
    }
}
