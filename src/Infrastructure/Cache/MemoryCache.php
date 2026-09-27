<?php
/**
 * Process-local cache used by tests and by a single request.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MemoryCache implements CacheInterface {

    /** @var array<string, array{value: mixed, expires: int}> */
    private array $items = [];

    public function __construct( private readonly string $group = 'querynova' ) {
    }

    public function get( string $key, mixed $fallback = null ): mixed {
        $item = $this->items[ $this->id( $key ) ] ?? null;
        if ( $item === null || $item['expires'] < time() ) {
            return $fallback;
        }

        return $item['value'];
    }

    public function set( string $key, mixed $value, int $ttl ): void {
        $this->items[ $this->id( $key ) ] = [
            'value'   => $value,
            'expires' => time() + max( 1, $ttl ),
        ];
    }

    public function delete( string $key ): void {
        unset( $this->items[ $this->id( $key ) ] );
    }

    public function flushGroup(): void {
        $this->items = [];
    }

    private function id( string $key ): string {
        return $this->group . ':' . $key;
    }
}
