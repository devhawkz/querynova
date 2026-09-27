<?php
/**
 * Keyword fixture. It does not call an ads API.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Providers;

use QueryNova\Modules\Keywords\Domain\AdsKeywordProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FakeKeywordProvider implements AdsKeywordProvider {

    /**
     * @param array{volume: int|null, cpc: string|null, paid_competition: float|null, trend: list<float>|null}|null $metrics
     */
    public function __construct( private readonly ?array $metrics ) {
    }

    public function id(): string {
        return 'fake-keyword';
    }

    public function metrics( string $keyword, string $country, string $language ): ?array {
        unset( $keyword, $country, $language );

        return $this->metrics;
    }
}
