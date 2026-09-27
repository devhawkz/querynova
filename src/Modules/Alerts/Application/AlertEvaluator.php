<?php
/**
 * Alerts from supplied evidence. A missing number does not become a loss.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Alerts\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AlertEvaluator {

    /**
     * A rank drop of this many positions is QueryNova's major-loss rule, not a search-engine rule.
     */
    private const RANK_DROP = 10;

    /**
     * @param array<string, mixed> $input
     * @return list<array{type: string, severity: string, title: string, message: string}>
     */
    public function evaluate( array $input ): array {
        $alerts = [];
        if ( ( $input['indexability'] ?? null ) === 'noindex' ) {
            $alerts[] = $this->alert( 'deindexing', 'high', 'Indexability changed to noindex', 'The supplied indexability is noindex.' );
        }
        $rank = $this->drop( $input['previous_rank'] ?? null, $input['current_rank'] ?? null, true );
        if ( $rank !== null && $rank >= self::RANK_DROP ) {
            $alerts[] = $this->alert( 'major_rank_loss', 'high', 'Major rank loss', 'The supplied rank dropped by at least ' . self::RANK_DROP . ' positions. This is QueryNova\'s alert rule, not a search-engine rule.' );
        }
        $revenue = $this->drop( $input['previous_revenue'] ?? null, $input['current_revenue'] ?? null, false );
        if ( $revenue !== null && $revenue > 0 ) {
            $alerts[] = $this->alert( 'revenue_loss', 'high', 'Revenue loss', 'The supplied current revenue is lower than the supplied previous revenue.' );
        }
        $traffic = $this->ratioDrop( $input['previous_traffic'] ?? null, $input['current_traffic'] ?? null );
        if ( $traffic ) {
            $alerts[] = $this->alert( 'traffic_anomaly', 'medium', 'Traffic anomaly', 'The supplied traffic is under half of the supplied previous period.' );
        }
        $conversion = $this->ratioDrop( $input['previous_conversion'] ?? null, $input['current_conversion'] ?? null );
        if ( $conversion ) {
            $alerts[] = $this->alert( 'conversion_collapse', 'medium', 'Conversion collapse', 'The supplied conversion rate is under half of the supplied previous rate.' );
        }
        if ( ( $input['schema_failure'] ?? null ) === true ) {
            $alerts[] = $this->alert( 'schema_failure', 'medium', 'Schema failure', 'A schema failure was supplied.' );
        }
        if ( ( $input['provider_failure'] ?? null ) === true ) {
            $alerts[] = $this->alert( 'provider_failure', 'medium', 'Provider failure', 'A provider failure was supplied.' );
        }
        if ( ( $input['feed_issue'] ?? null ) === true ) {
            $alerts[] = $this->alert( 'product_feed_issue', 'medium', 'Product feed issue', 'A product feed issue was supplied.' );
        }
        $ai = $this->drop( $input['previous_ai'] ?? null, $input['current_ai'] ?? null, false );
        if ( $ai !== null && $ai > 0 ) {
            $alerts[] = $this->alert( 'ai_visibility_decline', 'medium', 'AI visibility decline', 'The supplied AI visibility value is lower than the previous supplied value. This is not an official provider ranking.' );
        }
        if ( ( $input['competitor_movement'] ?? null ) === true ) {
            $alerts[] = $this->alert( 'competitor_movement', 'low', 'Competitor movement', 'Competitor movement was supplied.' );
        }

        return $alerts;
    }

    /**
     * @return array{type: string, severity: string, title: string, message: string}
     */
    private function alert( string $type, string $severity, string $title, string $message ): array {
        return [
            'type'     => $type,
            'severity' => $severity,
            'title'    => $title,
            'message'  => $message,
        ];
    }

    /**
     * Positive means the current value moved in the loss direction.
     */
    private function drop( mixed $previous, mixed $current, bool $higherIsWorse ): ?float {
        if ( ! is_int( $previous ) && ! is_float( $previous ) ) {
            return null;
        }
        if ( ! is_int( $current ) && ! is_float( $current ) ) {
            return null;
        }
        $delta = (float) $current - (float) $previous;

        return $higherIsWorse ? $delta : -$delta;
    }

    private function ratioDrop( mixed $previous, mixed $current ): bool {
        if ( ! is_int( $previous ) && ! is_float( $previous ) ) {
            return false;
        }
        if ( ! is_int( $current ) && ! is_float( $current ) ) {
            return false;
        }
        if ( (float) $previous <= 0 ) {
            return false;
        }

        return (float) $current < ( (float) $previous / 2 );
    }
}
