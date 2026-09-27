<?php
/**
 * Service container contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ContainerInterface {

    public function set( string $id, mixed $concrete ): void;

    /**
     * @param callable(ContainerInterface): mixed $factory
     */
    public function singleton( string $id, callable $factory ): void;

    /**
     * @param callable(ContainerInterface): mixed $factory
     */
    public function factory( string $id, callable $factory ): void;

    public function get( string $id ): mixed;

    public function has( string $id ): bool;
}
