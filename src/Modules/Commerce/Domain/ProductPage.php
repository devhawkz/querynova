<?php
/**
 * One page of products. Total is null when the catalog was not measured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductPage {

    /**
     * @param list<CatalogProduct> $products
     */
    public function __construct(
        public readonly array $products,
        public readonly ?int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly bool $active,
    ) {
    }
}
