<?php
/**
 * Transient lock with an expiry so a crashed worker cannot hold it forever.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Lock;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TransientLock implements LockInterface {

    public function acquire( string $name, int $ttlSeconds ): bool {
        $key = $this->key( $name );
        if ( get_transient( $key ) ) {
            return false;
        }
        set_transient( $key, '1', $ttlSeconds );

        return true;
    }

    public function release( string $name ): void {
        delete_transient( $this->key( $name ) );
    }

    private function key( string $name ): string {
        return 'querynova_lock_' . md5( $name );
    }
}
