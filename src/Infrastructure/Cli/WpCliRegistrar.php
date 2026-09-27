<?php
/**
 * Registers wp querynova only when WP-CLI is running.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cli;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WpCliRegistrar {

    public static function register( CliCommands $commands ): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
            return;
        }

        \WP_CLI::add_command(
            'querynova',
            static function ( array $args, array $assoc ) use ( $commands ): void {
                $json   = ( $assoc['format'] ?? '' ) === 'json';
                $result = $commands->execute( self::words( $args ), self::flags( $assoc ) );
                $body   = $commands->render( $result, $json );
                if ( ( $result['ok'] ?? false ) !== true ) {
                    \WP_CLI::error( $body );
                }
                \WP_CLI::log( $body );
            }
        );
    }

    /**
     * @param array<int, mixed> $args
     * @return list<string>
     */
    private static function words( array $args ): array {
        $words = [];
        foreach ( $args as $arg ) {
            if ( is_string( $arg ) && $arg !== '' ) {
                $words[] = $arg;
            }
        }

        return $words;
    }

    /**
     * @param array<string, mixed> $assoc
     * @return array<string, mixed>
     */
    private static function flags( array $assoc ): array {
        $flags = [];
        foreach ( [ 'id', 'url', 'property', 'start', 'end' ] as $key ) {
            if ( isset( $assoc[ $key ] ) && ( is_string( $assoc[ $key ] ) || is_int( $assoc[ $key ] ) ) ) {
                $flags[ $key ] = $assoc[ $key ];
            }
        }

        return $flags;
    }
}
