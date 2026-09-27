<?php
/**
 * Timings stay null when the provider omitted them. They are not an SEO score.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experience\Application;

use QueryNova\Core\Domain\ProvenanceKind;
use QueryNova\Modules\Experience\Domain\ExperienceReport;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperienceSummary {

    /**
     * @return array<string, mixed>
     */
    public function summarize( ?ExperienceReport $report, string $strategy ): array {
        if ( ! in_array( $strategy, [ 'desktop', 'mobile' ], true ) ) {
            return $this->empty( 'Strategy must be desktop or mobile.' );
        }
        if ( ! $report instanceof ExperienceReport ) {
            return $this->empty( 'Page experience was not measured.' );
        }

        return [
            'status'    => ProvenanceKind::Measured->value,
            'strategy'  => $strategy,
            'lcp'       => $this->metric( $report->lcp ),
            'inp'       => $this->metric( $report->inp ),
            'cls'       => $this->metric( $report->cls ),
            'ttfb'      => $this->metric( $report->ttfb ),
            'seo_score' => null,
            'note'      => 'These are page experience timings. They are not an SEO score.',
        ];
    }

    /**
     * @return array{value: float|null, provenance: string}
     */
    private function metric( ?float $value ): array {
        if ( $value === null ) {
            return [
                'value'      => null,
                'provenance' => ProvenanceKind::Unavailable->value,
            ];
        }

        return [
            'value'      => $value,
            'provenance' => ProvenanceKind::Measured->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty( string $note ): array {
        $metric = [
            'value'      => null,
            'provenance' => ProvenanceKind::Unavailable->value,
        ];

        return [
            'status'    => ProvenanceKind::Unavailable->value,
            'strategy'  => null,
            'lcp'       => $metric,
            'inp'       => $metric,
            'cls'       => $metric,
            'ttfb'      => $metric,
            'seo_score' => null,
            'note'      => $note . ' These timings are not an SEO score.',
        ];
    }
}
