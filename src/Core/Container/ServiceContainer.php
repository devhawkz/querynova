<?php
/**
 * Explicit constructor-injection container. No service locator singletons.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ServiceContainer implements ContainerInterface {

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, callable(ContainerInterface): mixed> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function set( string $id, mixed $concrete ): void {
        $this->instances[ $id ] = $concrete;
        unset( $this->factories[ $id ], $this->shared[ $id ] );
    }

    public function singleton( string $id, callable $factory ): void {
        $this->factories[ $id ] = $factory;
        $this->shared[ $id ]    = true;
        unset( $this->instances[ $id ] );
    }

    public function factory( string $id, callable $factory ): void {
        $this->factories[ $id ] = $factory;
        $this->shared[ $id ]    = false;
        unset( $this->instances[ $id ] );
    }

    public function get( string $id ): mixed {
        if ( array_key_exists( $id, $this->instances ) ) {
            return $this->instances[ $id ];
        }

        if ( ! isset( $this->factories[ $id ] ) ) {
            throw new NotFoundException( $id );
        }

        if ( isset( $this->resolving[ $id ] ) ) {
            throw new ContainerException( sprintf( 'Circular service dependency while resolving "%s".', $id ) );
        }

        $this->resolving[ $id ] = true;
        try {
            $object = ( $this->factories[ $id ] )( $this );
        } finally {
            unset( $this->resolving[ $id ] );
        }

        if ( $this->shared[ $id ] ?? false ) {
            $this->instances[ $id ] = $object;
        }

        return $object;
    }

    public function has( string $id ): bool {
        return array_key_exists( $id, $this->instances ) || isset( $this->factories[ $id ] );
    }
}
