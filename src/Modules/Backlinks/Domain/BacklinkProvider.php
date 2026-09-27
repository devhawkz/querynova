<?php
/**
 * Backlink provider contract. A null payload means the data is unavailable.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface BacklinkProvider {

    public function id(): string;

    /**
     * The provider's name for its authority number, such as the vendor's own metric.
     * Empty when the provider has no authority metric.
     */
    public function authorityMetric(): string;

    /**
     * @return list<Backlink>|null
     */
    public function links( string $targetUrl ): ?array;
}
