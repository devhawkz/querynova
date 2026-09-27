<?php
/**
 * Commerce aggregates. A null return means orders were not read.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface CommerceMetricsProvider {

    public function id(): string;

    /**
     * @return list<CommerceMetricRow>|null
     */
    public function rows( string $start, string $end ): ?array;
}
