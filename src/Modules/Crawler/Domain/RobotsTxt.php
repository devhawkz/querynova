<?php
/**
 * Reads User-agent: * disallow rules. Other agents are ignored.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RobotsTxt {

    /**
     * @return list<string>
     */
    public function disallows( string $body ): array {
        $rules   = [];
        $applies = true;
        $lines   = preg_split( '/\r\n|\n|\r/', $body );
        if ( ! is_array( $lines ) ) {
            return [];
        }
        foreach ( $lines as $line ) {
            $line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
            if ( $line === '' || ! str_contains( $line, ':' ) ) {
                continue;
            }
            [ $field, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
            $field             = strtolower( $field );
            if ( $field === 'user-agent' ) {
                $applies = $value === '*';
                continue;
            }
            if ( $applies && $field === 'disallow' && $value !== '' ) {
                $rules[] = $value;
            }
        }

        return array_values( array_unique( $rules ) );
    }

    /**
     * @param list<string> $rules
     */
    public function blocks( string $path, array $rules ): bool {
        if ( $path === '' ) {
            $path = '/';
        }
        foreach ( $rules as $rule ) {
            if ( $rule !== '' && str_starts_with( $path, $rule ) ) {
                return true;
            }
        }

        return false;
    }
}
