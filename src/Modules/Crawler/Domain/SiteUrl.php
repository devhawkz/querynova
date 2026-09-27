<?php
/**
 * URL helpers for the crawler. Fragments are ignored. Only http(s) remains.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SiteUrl {

    public static function host( string $url ): string {
        $parts = wp_parse_url( $url );

        return is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';
    }

    public static function origin( string $url ): string {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return '';
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        $host   = strtolower( (string) ( $parts['host'] ?? '' ) );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || $host === '' ) {
            return '';
        }

        return $scheme . '://' . $host;
    }

    public static function normalize( string $url ): string {
        $url  = trim( $url );
        $hash = strpos( $url, '#' );
        if ( $hash !== false ) {
            $url = substr( $url, 0, $hash );
        }
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return '';
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        $host   = strtolower( (string) ( $parts['host'] ?? '' ) );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || $host === '' ) {
            return '';
        }
        $path  = (string) ( $parts['path'] ?? '/' );
        $path  = $path === '' ? '/' : $path;
        $query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
        if ( $path !== '/' ) {
            $path = rtrim( $path, '/' );
        }

        return $scheme . '://' . $host . $path . $query;
    }

    public static function resolve( string $base, string $href ): string {
        $href = trim( $href );
        if ( $href === '' || str_starts_with( $href, '#' ) || preg_match( '/^(mailto|tel|javascript):/i', $href ) === 1 ) {
            return '';
        }
        if ( str_starts_with( $href, '//' ) ) {
            $scheme = strtolower( (string) ( wp_parse_url( $base, PHP_URL_SCHEME ) ?? 'https' ) );
            $href   = $scheme . ':' . $href;
        }
        if ( ! preg_match( '#^https?://#i', $href ) ) {
            $origin = self::origin( $base );
            if ( $origin === '' ) {
                return '';
            }
            if ( str_starts_with( $href, '/' ) ) {
                $href = $origin . $href;
            } else {
                $path = (string) ( wp_parse_url( $base, PHP_URL_PATH ) ?? '/' );
                $dir  = str_contains( $path, '/' ) ? substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) : '/';
                $href = $origin . self::collapse( $dir . $href );
            }
        }

        return self::normalize( $href );
    }

    public static function sameHost( string $left, string $right ): bool {
        $host = self::host( $left );

        return $host !== '' && $host === self::host( $right );
    }

    public static function path( string $url ): string {
        $path = wp_parse_url( $url, PHP_URL_PATH );

        return is_string( $path ) && $path !== '' ? $path : '/';
    }

    private static function collapse( string $path ): string {
        $parts = [];
        foreach ( explode( '/', $path ) as $part ) {
            if ( $part === '' || $part === '.' ) {
                continue;
            }
            if ( $part === '..' ) {
                array_pop( $parts );
                continue;
            }
            $parts[] = $part;
        }

        return '/' . implode( '/', $parts );
    }
}
