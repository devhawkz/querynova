<?php
/**
 * A category or brand term. Product count is measured by WooCommerce when present.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CatalogTerm {

    public function __construct(
        public readonly int $id,
        public readonly string $taxonomy,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly ?int $count,
        public readonly int $parentId,
    ) {
    }
}
