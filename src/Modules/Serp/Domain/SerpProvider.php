<?php
/**
 * SERP provider contract. Implementations must not scrape Google.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface SerpProvider {

    public function id(): string;

    public function capabilities(): ProviderCapabilities;

    /**
     * Null means the provider has no snapshot. It is not an empty result list.
     */
    public function snapshot( SerpQuery $query ): ?SerpSnapshot;
}
