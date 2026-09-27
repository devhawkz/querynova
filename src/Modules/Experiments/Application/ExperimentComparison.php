<?php
/**
 * Before/after observations. Movement is not treated as proof of causation.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experiments\Application;

use QueryNova\Core\Domain\ProvenanceKind;
use QueryNova\Core\Exceptions\ValidationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperimentComparison {

    public const NOTE = 'Before and after are observations. They do not show that the change caused the result.';

    /**
     * @var list<string>
     */
    public const TYPES = [
        'title_change',
        'description_change',
        'category_content',
        'internal_links',
        'schema_change',
        'other',
    ];

    /**
     * Lower rank is the favorable direction. The other metrics improve when they rise.
     *
     * @var list<string>
     */
    private const METRICS = [ 'rank', 'ctr', 'clicks', 'traffic', 'revenue' ];

    /**
     * @param array<string, float|int|null> $before
     * @param array<string, float|int|null> $after
     * @return array<string, mixed>
     */
    public function compare( string $type, array $before, array $after ): array {
        if ( ! in_array( $type, self::TYPES, true ) ) {
            throw new ValidationException( 'Unknown experiment type.' );
        }
        $metrics    = [];
        $directions = [];
        $compared   = false;
        foreach ( self::METRICS as $metric ) {
            $row                = $this->metric( $metric, $before[ $metric ] ?? null, $after[ $metric ] ?? null );
            $metrics[ $metric ] = $row;
            $directions[]       = $row['direction'];
            if ( $row['provenance'] === ProvenanceKind::Measured->value ) {
                $compared = true;
            }
        }

        return [
            'type'      => $type,
            'status'    => $compared ? ProvenanceKind::Measured->value : ProvenanceKind::Unavailable->value,
            'result'    => $this->verdict( $directions ),
            'metrics'   => $metrics,
            'causation' => false,
            'applied'   => false,
            'note'      => self::NOTE,
        ];
    }

    /**
     * @return array{before: float|null, after: float|null, delta: float|null, direction: string|null, provenance: string}
     */
    private function metric( string $name, mixed $before, mixed $after ): array {
        $start = $this->number( $before );
        $end   = $this->number( $after );
        if ( $start === null || $end === null ) {
            return [
                'before'     => $start,
                'after'      => $end,
                'delta'      => null,
                'direction'  => null,
                'provenance' => ProvenanceKind::Unavailable->value,
            ];
        }
        $delta = round( $end - $start, 6 );

        return [
            'before'     => $start,
            'after'      => $end,
            'delta'      => $delta,
            'direction'  => $this->direction( $name, $delta ),
            'provenance' => ProvenanceKind::Measured->value,
        ];
    }

    private function direction( string $name, float $delta ): ?string {
        if ( $delta === 0.0 ) {
            return null;
        }
        $improved = $name === 'rank' ? $delta < 0 : $delta > 0;

        return $improved ? 'improved' : 'declined';
    }

    /**
     * @param list<string|null> $directions
     */
    private function verdict( array $directions ): string {
        $improved = false;
        $declined = false;
        foreach ( $directions as $direction ) {
            if ( $direction === 'improved' ) {
                $improved = true;
            }
            if ( $direction === 'declined' ) {
                $declined = true;
            }
        }
        if ( $improved && $declined ) {
            return 'mixed';
        }
        if ( $improved ) {
            return 'improved';
        }
        if ( $declined ) {
            return 'declined';
        }

        return 'inconclusive';
    }

    private function number( mixed $value ): ?float {
        if ( ! is_int( $value ) && ! is_float( $value ) ) {
            return null;
        }

        return (float) $value;
    }
}
