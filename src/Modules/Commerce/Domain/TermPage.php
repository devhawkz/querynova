<?php
/**
 * One page of categories or brands.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TermPage {

    /**
     * @param list<CatalogTerm> $terms
     */
    public function __construct(
        public readonly array $terms,
        public readonly ?int $total,
        public readonly bool $active,
    ) {
    }
}
