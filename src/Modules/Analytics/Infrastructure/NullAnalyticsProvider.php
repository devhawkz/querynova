<?php
/**
 * Disconnected GA4 adapter. It does not call Google.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Infrastructure;

use QueryNova\Modules\Analytics\Domain\AnalyticsProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullAnalyticsProvider implements AnalyticsProvider {

    public function id(): string {
        return '';
    }

    public function rows( string $property, string $start, string $end ): ?array {
        unset( $property, $start, $end );

        return null;
    }
}
