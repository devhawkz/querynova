<?php
/**
 * In-memory commerce API for tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Support;

use QueryNova\Modules\Commerce\Domain\CommerceApi;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ArrayCommerceApi implements CommerceApi {

    public int $orderCalls = 0;

    /** @var array<string, mixed> */
    public array $productArgs = [];

    /**
     * @param list<array{id: int, name: string, slug: string, description: string, count: int, parent: int, taxonomy: string}> $terms
     */
    public function __construct(
        private readonly bool $active,
        private readonly mixed $products = null,
        private readonly mixed $orders = null,
        private readonly array $terms = [],
    ) {
    }

    public function available(): bool {
        return $this->active;
    }

    public function products( array $args ): mixed {
        $this->productArgs = $args;

        return $this->products;
    }

    public function orders( array $args ): mixed {
        unset( $args );
        ++$this->orderCalls;

        return $this->orders;
    }

    public function terms( string $taxonomy ): array {
        $rows = [];
        foreach ( $this->terms as $term ) {
            if ( $term['taxonomy'] === $taxonomy ) {
                $rows[] = $term;
            }
        }

        return $rows;
    }
}
