<?php
/**
 * Module job handler registration.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class JobRegistrar {

    /** @var array<string, callable(array<string, mixed>, array<string, mixed>): void> */
    private array $handlers = [];

    /**
     * @param callable(array<string, mixed>, array<string, mixed>): void $handler
     */
    public function register( string $type, callable $handler ): void {
        $this->handlers[ $type ] = $handler;
    }

    public function has( string $type ): bool {
        return isset( $this->handlers[ $type ] );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $job
     */
    public function handle( string $type, array $payload, array $job ): void {
        if ( ! isset( $this->handlers[ $type ] ) ) {
            throw new \RuntimeException( sprintf( 'No handler for job type "%s".', $type ) );
        }
        ( $this->handlers[ $type ] )( $payload, $job );
    }
}
