<?php
/**
 * GA4 import. A null return means analytics are not connected.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface AnalyticsProvider {

    public function id(): string;

    /**
     * @return list<AnalyticsRow>|null
     */
    public function rows( string $property, string $start, string $end ): ?array;
}
