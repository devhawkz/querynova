<?php
/**
 * Analytics fixture. It does not call GA4.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Providers;

use QueryNova\Modules\Analytics\Domain\AnalyticsProvider;
use QueryNova\Modules\Analytics\Domain\AnalyticsRow;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FakeAnalyticsProvider implements AnalyticsProvider {

    /**
     * @param list<AnalyticsRow>|null $rows
     */
    public function __construct( private readonly ?array $rows ) {
    }

    public function id(): string {
        return 'fake-analytics';
    }

    public function rows( string $property, string $start, string $end ): ?array {
        unset( $property, $start, $end );

        return $this->rows;
    }
}
