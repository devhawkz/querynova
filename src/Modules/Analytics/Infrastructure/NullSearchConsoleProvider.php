<?php
/**
 * Disconnected Search Console adapter. It does not call Google.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Infrastructure;

use QueryNova\Modules\Analytics\Domain\SearchConsoleProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullSearchConsoleProvider implements SearchConsoleProvider {

    public function id(): string {
        return '';
    }

    public function rows( string $property, string $start, string $end ): ?array {
        unset( $property, $start, $end );

        return null;
    }
}
