<?php
/**
 * Fixed paid metrics. Organic difficulty is intentionally absent.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Support;

use QueryNova\Modules\Keywords\Domain\AdsKeywordProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FixedAdsProvider implements AdsKeywordProvider {

    public function id(): string {
        return 'fixture';
    }

    public function metrics( string $keyword, string $country, string $language ): ?array {
        unset( $keyword, $country, $language );

        return [
            'volume'           => 90,
            'cpc'              => '1.20',
            'paid_competition' => 0.8,
            'trend'            => [ 1.0, 2.0 ],
        ];
    }
}
