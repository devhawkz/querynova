<?php
/**
 * WooCommerce calls used by the gateway. Implementations must not query order tables.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface CommerceApi {

    public function available(): bool;

    /**
     * @param array<string, mixed> $args
     */
    public function products( array $args ): mixed;

    /**
     * Order reads go through WooCommerce. HPOS stays behind that API.
     *
     * @param array<string, mixed> $args
     */
    public function orders( array $args ): mixed;

    /**
     * @return list<array{id: int, name: string, slug: string, description: string, count: int, parent: int, taxonomy: string}>
     */
    public function terms( string $taxonomy ): array;
}
