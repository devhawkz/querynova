<?php
/**
 * Product double with the WooCommerce method names the mapper calls.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FakeWcProduct {

    /**
     * @param array<string, mixed> $data
     */
    public function __construct( private readonly array $data ) {
    }

    public function get_id(): int {
        return (int) ( $this->data['id'] ?? 0 );
    }

    public function get_parent_id(): int {
        return (int) ( $this->data['parent_id'] ?? 0 );
    }

    public function get_name(): string {
        return (string) ( $this->data['name'] ?? '' );
    }

    public function get_short_description(): string {
        return (string) ( $this->data['short_description'] ?? '' );
    }

    public function get_description(): string {
        return (string) ( $this->data['description'] ?? '' );
    }

    public function get_permalink(): string {
        return (string) ( $this->data['url'] ?? '' );
    }

    public function get_sku(): string {
        return (string) ( $this->data['sku'] ?? '' );
    }

    public function get_price(): string {
        return (string) ( $this->data['price'] ?? '' );
    }

    public function get_sale_price(): string {
        return (string) ( $this->data['sale_price'] ?? '' );
    }

    public function get_stock_status(): string {
        return (string) ( $this->data['stock_status'] ?? '' );
    }

    public function get_image_id(): int {
        return (int) ( $this->data['image_id'] ?? 0 );
    }

    /**
     * @return list<int>
     */
    public function get_gallery_image_ids(): array {
        $ids = $this->data['gallery'] ?? [];

        return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
    }

    public function get_attribute( string $name ): string {
        $attributes = $this->data['attributes'] ?? [];

        return is_array( $attributes ) ? (string) ( $attributes[ $name ] ?? '' ) : '';
    }

    public function get_global_unique_id(): string {
        return (string) ( $this->data['gtin'] ?? '' );
    }

    public function get_meta( string $key ): string {
        $meta = $this->data['meta'] ?? [];

        return is_array( $meta ) ? (string) ( $meta[ $key ] ?? '' ) : '';
    }

    /**
     * @return list<int>
     */
    public function get_category_ids(): array {
        $ids = $this->data['categories'] ?? [];

        return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
    }

    /**
     * @return list<int>
     */
    public function get_children(): array {
        return [];
    }

    public function get_review_count(): int {
        return (int) ( $this->data['review_count'] ?? 0 );
    }

    public function get_average_rating(): string {
        return (string) ( $this->data['rating'] ?? '' );
    }

    /**
     * @return list<int>
     */
    public function get_upsell_ids(): array {
        return [];
    }

    /**
     * @return list<int>
     */
    public function get_cross_sell_ids(): array {
        return [];
    }
}
