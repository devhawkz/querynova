<?php
/**
 * Blocks crawler and import fetches to private networks and unsafe protocols.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Security;

use QueryNova\Core\Exceptions\ValidationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SsrfGuard {

    /** @var callable(string): list<string> */
    private $resolver;

    /**
     * @param callable(string): list<string>|null $resolver
     */
    public function __construct( ?callable $resolver = null ) {
        $this->resolver = $resolver ?? static function ( string $host ): array {
            if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
                return [ $host ];
            }
            $records = gethostbynamel( $host );

            return is_array( $records ) ? $records : [];
        };
    }

    public function assertSafe( string $url ): void {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            throw new ValidationException( 'The URL could not be parsed.' );
        }

        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            throw new ValidationException( 'Only http and https URLs can be fetched.' );
        }

        $host = strtolower( (string) ( $parts['host'] ?? '' ) );
        if ( $host === '' || $this->isBlockedHost( $host ) ) {
            throw new ValidationException( 'That host is not allowed.' );
        }

        $port = isset( $parts['port'] ) ? (int) $parts['port'] : ( $scheme === 'https' ? 443 : 80 );
        if ( ! in_array( $port, [ 80, 443, 8080, 8443 ], true ) ) {
            throw new ValidationException( 'That port is not allowed.' );
        }

        $ips = ( $this->resolver )( $host );
        if ( $ips === [] ) {
            throw new ValidationException( 'The host did not resolve.' );
        }

        foreach ( $ips as $ip ) {
            if ( $this->isPrivateOrReserved( $ip ) ) {
                throw new ValidationException( 'Private and reserved network addresses are blocked.' );
            }
        }
    }

    public function isPrivateOrReserved( string $ip ): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function isBlockedHost( string $host ): bool {
        $blocked = [
            'localhost',
            'localhost.localdomain',
            'metadata.google.internal',
            'metadata.internal',
        ];
        if ( in_array( $host, $blocked, true ) ) {
            return true;
        }

        return str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' );
    }
}
