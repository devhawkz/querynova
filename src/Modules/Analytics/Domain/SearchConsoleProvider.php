<?php
/**
 * Search Console import. A null return means the property is not connected.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface SearchConsoleProvider {

    public function id(): string;

    /**
     * @return list<SearchConsoleRow>|null
     */
    public function rows( string $property, string $start, string $end ): ?array;
}
