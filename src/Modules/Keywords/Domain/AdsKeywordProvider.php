<?php
/**
 * Optional paid-search metrics. Paid competition is not organic difficulty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Keywords\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface AdsKeywordProvider {

    public function id(): string;

    /**
     * @return array{volume: int|null, cpc: string|null, paid_competition: float|null, trend: list<float>|null}|null
     */
    public function metrics( string $keyword, string $country, string $language ): ?array;
}
