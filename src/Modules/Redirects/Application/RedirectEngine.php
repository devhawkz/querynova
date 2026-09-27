<?php
/**
 * Validates redirects and resolves a path, including chains.
 *
 * A loop is refused. A chain is followed, up to five hops, so the response is one redirect.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Redirects\Domain\RedirectDecision;
use QueryNova\Modules\Redirects\Domain\RedirectRule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectEngine {

    public const STATUSES = [ 301, 302, 307, 410, 451 ];

    /**
     * @param list<RedirectRule> $rules
     */
    public function assertSafe( array $rules, RedirectRule $candidate ): void {
        $this->normalizeSource( $candidate->source(), $candidate->regex() );
        $this->normalizeTarget( $candidate->target(), $candidate->status() );
        $next = $this->replace( $rules, $candidate );
        if ( $this->resolve( $candidate->regex() ? $candidate->target() : $candidate->source(), $next )->loop() ) {
            throw new ValidationException( 'This redirect creates a loop.' );
        }
        foreach ( $next as $rule ) {
            if ( $rule->regex() || ! $rule->enabled() ) {
                continue;
            }
            if ( $this->resolve( $rule->source(), $next )->loop() ) {
                throw new ValidationException( 'This redirect creates a loop.' );
            }
        }
    }

    /**
     * @param list<RedirectRule> $rules
     */
    public function resolve( string $path, array $rules ): RedirectDecision {
        try {
            $current = $this->normalizeSource( $path, false );
        } catch ( ValidationException ) {
            return new RedirectDecision( false, 0, '', 0, false );
        }
        $seen     = [];
        $first    = null;
        $status   = 0;
        $location = '';
        for ( $hop = 0; $hop < 5; $hop++ ) {
            if ( isset( $seen[ $current ] ) ) {
                return new RedirectDecision( false, 0, '', 0, true );
            }
            $seen[ $current ] = true;
            $rule             = $this->match( $current, $rules );
            if ( ! $rule instanceof RedirectRule ) {
                break;
            }
            if ( ! $first instanceof RedirectRule ) {
                $first = $rule;
            }
            $status = $rule->status();
            if ( $status === 410 || $status === 451 ) {
                return new RedirectDecision( true, $status, '', $first->id(), false );
            }
            $location = $this->apply( $rule, $current );
            if ( $location === '' ) {
                return new RedirectDecision( false, 0, '', 0, true );
            }
            if ( $this->isHttp( $location ) ) {
                $current = $location;
                break;
            }
            try {
                $current = $this->normalizeSource( $location, false );
            } catch ( ValidationException ) {
                return new RedirectDecision( false, 0, '', 0, true );
            }
        }
        if ( ! $first instanceof RedirectRule ) {
            return new RedirectDecision( false, 0, '', 0, false );
        }
        if ( ! $this->isHttp( $current ) && $this->match( $current, $rules ) instanceof RedirectRule ) {
            return new RedirectDecision( false, 0, '', 0, true );
        }

        return new RedirectDecision( true, $status, $location, $first->id(), false );
    }

    /**
     * @param list<RedirectRule> $rules
     */
    public function suggest( string $path, array $rules ): string {
        $base = $this->basename( $path );
        if ( $base === '' ) {
            return '';
        }
        $targets = [];
        foreach ( $rules as $rule ) {
            if ( $rule->regex() || ! $rule->enabled() || $rule->target() === '' ) {
                continue;
            }
            if ( $this->basename( $rule->source() ) === $base ) {
                $targets[ $rule->target() ] = true;
            }
        }
        if ( count( $targets ) !== 1 ) {
            return '';
        }
        $target = array_key_first( $targets );

        return is_string( $target ) ? $target : '';
    }

    public function normalizeSource( string $source, bool $regex ): string {
        $source = trim( $source );
        if ( $regex ) {
            return $this->normalizePattern( $source );
        }
        if ( str_contains( $source, '://' ) ) {
            $parts  = wp_parse_url( $source );
            $path   = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';
            $query  = is_array( $parts ) && isset( $parts['query'] ) ? '?' . $parts['query'] : '';
            $source = $path . $query;
        }
        if ( $source === '' || ! str_starts_with( $source, '/' ) || str_starts_with( $source, '//' ) || str_contains( $source, '\\' ) || strlen( $source ) > 2048 ) {
            throw new ValidationException( 'Redirect sources must be site paths.' );
        }

        return $source;
    }

    public function normalizeTarget( string $target, int $status ): string {
        if ( ! in_array( $status, self::STATUSES, true ) ) {
            throw new ValidationException( 'Redirect status must be 301, 302, 307, 410, or 451.' );
        }
        $target = trim( $target );
        if ( $status === 410 || $status === 451 ) {
            return $target === '' ? '' : $this->requireSafeLocation( $target );
        }

        return $this->requireSafeLocation( $target );
    }

    /**
     * @param list<RedirectRule> $rules
     */
    private function match( string $path, array $rules ): ?RedirectRule {
        foreach ( $rules as $rule ) {
            if ( ! $rule->enabled() || $rule->regex() ) {
                continue;
            }
            if ( $rule->source() === $path ) {
                return $rule;
            }
        }
        foreach ( $rules as $rule ) {
            if ( ! $rule->enabled() || ! $rule->regex() ) {
                continue;
            }
            $matched = preg_match( '#' . $rule->source() . '#', $path );
            if ( $matched === 1 ) {
                return $rule;
            }
        }

        return null;
    }

    private function apply( RedirectRule $rule, string $path ): string {
        if ( ! $rule->regex() ) {
            return $rule->target();
        }
        $replaced = preg_replace( '#' . $rule->source() . '#', $rule->target(), $path, 1 );

        return is_string( $replaced ) ? $replaced : '';
    }

    /**
     * @param list<RedirectRule> $rules
     * @return list<RedirectRule>
     */
    private function replace( array $rules, RedirectRule $candidate ): array {
        $next  = [];
        $found = false;
        foreach ( $rules as $rule ) {
            if ( $candidate->id() > 0 && $rule->id() === $candidate->id() ) {
                $next[] = $candidate;
                $found  = true;
                continue;
            }
            if ( $rule->regex() === $candidate->regex() && $rule->source() === $candidate->source() ) {
                throw new ValidationException( 'A redirect for that path already exists.' );
            }
            $next[] = $rule;
        }
        if ( ! $found ) {
            $next[] = $candidate;
        }

        return $next;
    }

    private function normalizePattern( string $pattern ): string {
        $literal = preg_replace( '/[\^\$\.\[\]\(\)\*\+\?\{\}\|\\\\\\/]/', '', $pattern ) ?? '';
        if ( $pattern === '' || strlen( $pattern ) > 200 || strlen( $literal ) < 2 || str_contains( $pattern, '#' ) || ! str_starts_with( $pattern, '^' ) ) {
            throw new ValidationException( 'Regex redirects must be anchored patterns without a hash delimiter.' );
        }
        if ( preg_match( '#' . $pattern . '#', '' ) === false ) {
            throw new ValidationException( 'Regex redirect pattern is invalid.' );
        }

        return $pattern;
    }

    private function requireSafeLocation( string $target ): string {
        if ( str_starts_with( $target, '/' ) && ! str_starts_with( $target, '//' ) && ! str_contains( $target, '\\' ) ) {
            return $target;
        }
        if ( ! $this->isHttp( $target ) ) {
            throw new ValidationException( 'Redirect targets must be a site path or an http(s) URL.' );
        }

        return $target;
    }

    private function isHttp( string $url ): bool {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return false;
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );

        return in_array( $scheme, [ 'http', 'https' ], true ) && ( $parts['host'] ?? '' ) !== '';
    }

    private function basename( string $path ): string {
        $path = trim( $path );
        $path = strtok( $path, '?' );
        if ( ! is_string( $path ) ) {
            return '';
        }
        $path = trim( $path, '/' );
        if ( $path === '' || str_contains( $path, '/' ) ) {
            $base = strrchr( $path, '/' );
            $path = is_string( $base ) ? ltrim( $base, '/' ) : $path;
        }

        return $path;
    }
}
