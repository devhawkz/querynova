<?php
/**
 * Redacts secrets and personal data before a log record is stored.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogSanitizer {

    private const REDACTED = '[redacted]';

    /** @var list<string> */
    private array $sensitiveKeys = [
        'password',
        'pass',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'set-cookie',
        'email',
        'e_mail',
        'customer_name',
        'customer_email',
        'first_name',
        'last_name',
        'address',
        'address_1',
        'address_2',
        'phone',
        'billing',
        'shipping',
        'payment',
        'card',
        'card_number',
        'cc',
        'cvv',
        'ssn',
    ];

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function sanitize( array $context ): array {
        $clean = [];
        foreach ( $context as $key => $value ) {
            if ( $key === 'exception' && $value instanceof \Throwable ) {
                $clean['exception_class']   = $value::class;
                $clean['exception_code']    = (string) $value->getCode();
                $clean['exception_message'] = $this->scrubString( $value->getMessage() );
                continue;
            }
            $clean[ $key ] = $this->sanitizeValue( (string) $key, $value );
        }

        return $clean;
    }

    public function scrubString( string $value ): string {
        $value = preg_replace( '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer ' . self::REDACTED, $value ) ?? $value;
        $value = preg_replace( '/(api[_-]?key|token|secret|password)=([^&\s]+)/i', '$1=' . self::REDACTED, $value ) ?? $value;
        $value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', self::REDACTED, $value ) ?? $value;

        return $value;
    }

    private function sanitizeValue( string $key, mixed $value ): mixed {
        if ( $this->isSensitiveKey( $key ) ) {
            return self::REDACTED;
        }
        if ( is_array( $value ) ) {
            $nested = [];
            foreach ( $value as $childKey => $child ) {
                $nested[ $childKey ] = $this->sanitizeValue( (string) $childKey, $child );
            }

            return $nested;
        }
        if ( is_string( $value ) ) {
            return $this->scrubString( $value );
        }
        if ( is_scalar( $value ) || $value === null ) {
            return $value;
        }

        return self::REDACTED;
    }

    private function isSensitiveKey( string $key ): bool {
        $normalized = strtolower( str_replace( [ '-', ' ' ], '_', $key ) );
        if ( in_array( $normalized, $this->sensitiveKeys, true ) ) {
            return true;
        }

        foreach ( [ '_token', '_secret', '_password', '_api_key', '_email' ] as $suffix ) {
            if ( str_ends_with( $normalized, $suffix ) ) {
                return true;
            }
        }

        return false;
    }
}
