<?php
/**
 * Aggregated commerce counts. No customer identifiers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CommerceMetricRow {

    public function __construct(
        public readonly string $date,
        public readonly int $productId,
        public readonly int $categoryId,
        public readonly ?int $views,
        public readonly ?int $addToCart,
        public readonly ?int $checkouts,
        public readonly ?int $orders,
        public readonly ?float $revenue,
        public readonly ?float $refunds,
        public readonly ?float $quantity,
    ) {
    }
}
