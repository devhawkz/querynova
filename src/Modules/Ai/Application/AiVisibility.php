<?php
/**
 * Observational AI notes. The index is not an official provider ranking.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Core\Domain\ProvenanceKind;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AiVisibility {

    public const DISCLAIMER  = 'Not an official OpenAI, Google, Gemini or other provider ranking metric.';
    public const METHODOLOGY = 'querynova.ai_visibility_index';
    public const VERSION     = 'v1';

    /**
     * @param list<array{brand: bool, product: bool, cited: bool, commercial: bool, competitors: list<string>}>|null $runs
     * @return array<string, mixed>
     */
    public function commerce( ?array $runs ): array {
        if ( $runs === null ) {
            return [
                'status'              => ProvenanceKind::Unavailable->value,
                'brand_share'         => null,
                'product_mentions'    => null,
                'product_citations'   => null,
                'commercial_coverage' => null,
                'competitor_presence' => null,
                'disclaimer'          => self::DISCLAIMER,
            ];
        }
        $total      = count( $runs );
        $brand      = 0;
        $products   = 0;
        $citations  = 0;
        $commercial = 0;
        $covered    = 0;
        $rivals     = [];
        foreach ( $runs as $run ) {
            if ( $run['brand'] ) {
                ++$brand;
            }
            if ( $run['product'] ) {
                ++$products;
            }
            if ( $run['cited'] ) {
                ++$citations;
            }
            if ( $run['commercial'] ) {
                ++$commercial;
                if ( $run['product'] ) {
                    ++$covered;
                }
            }
            foreach ( $run['competitors'] as $name ) {
                if ( $name !== '' ) {
                    $rivals[ $name ] = true;
                }
            }
        }

        return [
            'status'              => ProvenanceKind::Measured->value,
            'brand_share'         => $total > 0 ? round( $brand / $total, 6 ) : null,
            'product_mentions'    => $products,
            'product_citations'   => $citations,
            'commercial_coverage' => $commercial > 0 ? round( $covered / $commercial, 6 ) : null,
            'competitor_presence' => array_keys( $rivals ),
            'disclaimer'          => self::DISCLAIMER,
            'note'                => 'These counts come from stored observations. They are not a model rank.',
        ];
    }

    /**
     * Equal-weight observational index. Any missing input keeps the value null.
     *
     * @param array<string, float|null> $inputs
     * @return array<string, mixed>
     */
    public function index( array $inputs ): array {
        $keys  = [ 'mention_coverage', 'citation_coverage', 'unique_urls', 'crawler_accessibility', 'entity_clarity', 'competitor_share' ];
        $total = 0.0;
        foreach ( $keys as $key ) {
            $value = $inputs[ $key ] ?? null;
            if ( ! is_float( $value ) && ! is_int( $value ) ) {
                return [
                    'status'      => ProvenanceKind::Unavailable->value,
                    'value'       => null,
                    'confidence'  => ConfidenceBand::Unknown->value,
                    'methodology' => null,
                    'disclaimer'  => self::DISCLAIMER,
                    'note'        => 'The index stays empty until every input is supplied.',
                ];
            }
            $total += (float) $value;
        }
        $count = count( $keys );

        return [
            'status'      => ProvenanceKind::Estimated->value,
            'value'       => round( $total / $count, 4 ),
            'confidence'  => ConfidenceBand::Low->value,
            'methodology' => self::METHODOLOGY,
            'version'     => self::VERSION,
            'disclaimer'  => self::DISCLAIMER,
            'note'        => 'Equal-weight average of the supplied observational inputs.',
        ];
    }

    /**
     * @param list<array{sessions: int|null, orders: int|null, revenue: float|null}>|null $rows
     * @return array<string, mixed>
     */
    public function referrals( ?array $rows ): array {
        if ( $rows === null ) {
            return [
                'status'   => ProvenanceKind::Unavailable->value,
                'sessions' => null,
                'orders'   => null,
                'revenue'  => null,
                'cvr'      => null,
                'aov'      => null,
                'note'     => 'AI referral analytics are unavailable until a measurable referrer is supplied.',
            ];
        }
        $sessions = 0;
        $orders   = 0;
        $revenue  = 0.0;
        foreach ( $rows as $row ) {
            if ( $row['sessions'] === null || $row['orders'] === null || $row['revenue'] === null ) {
                return [
                    'status'   => ProvenanceKind::Unavailable->value,
                    'sessions' => null,
                    'orders'   => null,
                    'revenue'  => null,
                    'cvr'      => null,
                    'aov'      => null,
                    'note'     => 'A referral row omitted sessions, orders, or revenue, so the total is unavailable.',
                ];
            }
            $sessions += $row['sessions'];
            $orders   += $row['orders'];
            $revenue  += $row['revenue'];
        }

        return [
            'status'   => ProvenanceKind::Measured->value,
            'sessions' => $sessions,
            'orders'   => $orders,
            'revenue'  => round( $revenue, 4 ),
            'cvr'      => $sessions > 0 ? round( $orders / $sessions, 6 ) : null,
            'aov'      => $orders > 0 ? round( $revenue / $orders, 4 ) : null,
            'note'     => 'Totals use only measurable AI referrer rows.',
        ];
    }
}
