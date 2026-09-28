<?php
/**
 * Build channel written by the admin build.
 *
 * A missing file is unknown. Debug is never on by default.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BuildChannel {

    public function __construct( private readonly string $path ) {
    }

    public static function installedChannel(): string {
        $path = defined( 'QUERYNOVA_PATH' ) ? QUERYNOVA_PATH . 'build/channel.json' : '';

        return ( new self( $path ) )->read()['channel'];
    }

    /**
     * @return array{channel: string, diagnostics: bool, debugDefault: false}
     */
    public function read(): array {
        if ( ! is_file( $this->path ) ) {
            return [
                'channel'      => 'unknown',
                'diagnostics'  => false,
                'debugDefault' => false,
            ];
        }
        $decoded = json_decode( (string) file_get_contents( $this->path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local build manifest, not a remote request.
        $channel = is_array( $decoded ) && isset( $decoded['channel'] ) && is_string( $decoded['channel'] ) ? $decoded['channel'] : 'unknown';
        if ( ! in_array( $channel, [ 'production', 'staging', 'development' ], true ) ) {
            $channel = 'unknown';
        }

        return [
            'channel'      => $channel,
            'diagnostics'  => $channel === 'staging',
            'debugDefault' => false,
        ];
    }
}
