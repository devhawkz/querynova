<?php
/**
 * Recommendation lifecycle. Improved or declined requires measured before and after.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Opportunities\Application;

use QueryNova\Core\Domain\ProvenanceKind;
use QueryNova\Modules\Experiments\Application\ExperimentComparison;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RecommendationOutcome {

    public function __construct( private readonly ExperimentComparison $comparison ) {
    }

    /**
     * @param array<string, float|int|null> $before
     * @param array<string, float|int|null> $after
     * @return array<string, mixed>
     */
    public function measure( array $before, array $after ): array {
        $report = $this->comparison->compare( 'other', $before, $after );
        if ( $report['status'] !== ProvenanceKind::Measured->value ) {
            return [
                'ready'     => false,
                'outcome'   => null,
                'causation' => false,
                'note'      => 'Performance was not measured, so the recommendation is not marked improved or declined.',
            ];
        }
        $result  = (string) $report['result'];
        $outcome = $result === 'improved' || $result === 'declined' ? $result : 'inconclusive';

        return [
            'ready'     => true,
            'outcome'   => $outcome,
            'movement'  => $result,
            'causation' => false,
            'note'      => ExperimentComparison::NOTE,
        ];
    }
}
