<?php
/**
 * Filtered log rows. Secrets are scrubbed and are not returned.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogViewer {

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string>      $filters
     * @return array<string, mixed>
     */
    public static function filter( array $rows, array $filters ): array {
        $sanitizer = new LogSanitizer();
        $matched   = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! self::matches( $row, $filters ) ) {
                continue;
            }
            $matched[] = [
                'level'           => self::text( $row['level'] ?? null ),
                'channel'         => self::text( $row['channel'] ?? null ),
                'module'          => self::text( $row['module'] ?? null ),
                'provider'        => self::text( $row['provider'] ?? null ),
                'logged_at'       => self::text( $row['logged_at'] ?? null ),
                'error_reference' => self::text( $row['error_reference'] ?? null ),
                'correlation_id'  => self::text( $row['correlation_id'] ?? null ),
                'message'         => $sanitizer->scrubString( is_string( $row['message'] ?? null ) ? $row['message'] : '' ),
            ];
        }

        return [
            'rows'    => $matched,
            'secrets' => false,
            'note'    => 'The log viewer omits secrets.',
        ];
    }

    /**
     * @param array<string, mixed>  $row
     * @param array<string, string> $filters
     */
    private static function matches( array $row, array $filters ): bool {
        foreach ( [ 'level', 'channel', 'module', 'provider', 'error_reference', 'correlation_id' ] as $key ) {
            $wanted = trim( $filters[ $key ] ?? '' );
            if ( $wanted === '' ) {
                continue;
            }
            if ( (string) ( $row[ $key ] ?? '' ) !== $wanted ) {
                return false;
            }
        }
        $date = trim( $filters['date'] ?? '' );
        if ( $date === '' ) {
            return true;
        }
        $logged = (string) ( $row['logged_at'] ?? '' );

        return str_starts_with( $logged, $date );
    }

    private static function text( mixed $value ): ?string {
        if ( ! is_string( $value ) || trim( $value ) === '' ) {
            return null;
        }

        return trim( $value );
    }
}
