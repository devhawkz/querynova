<?php
/**
 * No paid-search provider is configured. Metrics stay unavailable.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Keywords\Infrastructure;

use QueryNova\Modules\Keywords\Domain\AdsKeywordProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullAdsProvider implements AdsKeywordProvider {

    public function id(): string {
        return '';
    }

    public function metrics( string $keyword, string $country, string $language ): ?array {
        unset( $keyword, $country, $language );

        return null;
    }
}
