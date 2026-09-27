<?php
/**
 * Commerce fields used by the schema graph.
 *
 * Rating and review count stay null when they were not measured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CommerceFacts {

    /**
     * @param list<array{sku: string, name: string, price: string, currency: string, availability: string}> $variations
     * @param list<array{author: string, body: string, rating: float|null}>                                 $reviews
     */
    public function __construct(
        private readonly string $name = '',
        private readonly string $sku = '',
        private readonly string $price = '',
        private readonly string $currency = '',
        private readonly string $availability = '',
        private readonly string $brand = '',
        private readonly ?float $ratingValue = null,
        private readonly ?int $reviewCount = null,
        private readonly ?int $bestRating = null,
        private readonly array $variations = [],
        private readonly array $reviews = [],
    ) {
    }

    public function name(): string {
        return $this->name;
    }

    public function sku(): string {
        return $this->sku;
    }

    public function price(): string {
        return $this->price;
    }

    public function currency(): string {
        return $this->currency;
    }

    public function availability(): string {
        return $this->availability;
    }

    public function brand(): string {
        return $this->brand;
    }

    public function ratingValue(): ?float {
        return $this->ratingValue;
    }

    public function reviewCount(): ?int {
        return $this->reviewCount;
    }

    public function bestRating(): ?int {
        return $this->bestRating;
    }

    /**
     * @return list<array{sku: string, name: string, price: string, currency: string, availability: string}>
     */
    public function variations(): array {
        return $this->variations;
    }

    /**
     * @return list<array{author: string, body: string, rating: float|null}>
     */
    public function reviews(): array {
        return $this->reviews;
    }
}
