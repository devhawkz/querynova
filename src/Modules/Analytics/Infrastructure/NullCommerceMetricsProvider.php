<?php
/**
 * Disconnected commerce adapter. It does not read orders.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Infrastructure;

use QueryNova\Modules\Analytics\Domain\CommerceMetricsProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullCommerceMetricsProvider implements CommerceMetricsProvider {

    public function id(): string {
        return '';
    }

    public function rows( string $start, string $end ): ?array {
        unset( $start, $end );

        return null;
    }
}
