<?php
/**
 * Confidence band. Scores are not shown with fake precision.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum ConfidenceBand: string {

    case High    = 'HIGH';
    case Medium  = 'MEDIUM';
    case Low     = 'LOW';
    case Unknown = 'UNKNOWN';

    public static function fromRatio( float $ratio ): self {
        if ( $ratio >= 0.75 ) {
            return self::High;
        }
        if ( $ratio >= 0.45 ) {
            return self::Medium;
        }
        if ( $ratio > 0 ) {
            return self::Low;
        }

        return self::Unknown;
    }
}
