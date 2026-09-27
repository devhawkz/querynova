<?php
/**
 * JSON-lines file log with size rotation.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging\Handler;

// Log rotation runs in the plugin's own uploads directory. WP_Filesystem may prompt for credentials, which is wrong here.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.rename_rename

use QueryNova\Core\Logging\LogRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FileLogHandler implements LogHandlerInterface {

    public function __construct(
        private readonly string $path,
        private readonly int $maxBytes = 5_000_000,
        private readonly int $maxFiles = 5,
    ) {
    }

    public function handle( LogRecord $record ): void {
        $directory = dirname( $this->path );
        if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
            return;
        }

        $this->rotateIfNeeded();
        $encoded = wp_json_encode( $record->toArray() );
        if ( ! is_string( $encoded ) ) {
            return;
        }
        file_put_contents( $this->path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX );
    }

    public function path(): string {
        return $this->path;
    }

    private function rotateIfNeeded(): void {
        if ( ! is_file( $this->path ) || filesize( $this->path ) < $this->maxBytes ) {
            return;
        }

        for ( $index = $this->maxFiles - 1; $index >= 1; $index-- ) {
            $source = $this->path . '.' . $index;
            $target = $this->path . '.' . ( $index + 1 );
            if ( $index + 1 >= $this->maxFiles && is_file( $source ) ) {
                unlink( $source );
                continue;
            }
            if ( is_file( $source ) ) {
                rename( $source, $target );
            }
        }

        rename( $this->path, $this->path . '.1' );
    }
}
