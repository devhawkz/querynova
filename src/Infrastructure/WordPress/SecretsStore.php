<?php
/**
 * Server-side secret storage. Values are encrypted with the site auth salt.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\WordPress;

use QueryNova\Core\Exceptions\ConfigurationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SecretsStore {

    private const OPTION = 'querynova_credentials';

    public function __construct( private readonly OptionStore $options ) {
    }

    public function put( string $name, string $secret ): void {
        $all          = $this->allEncrypted();
        $all[ $name ] = $this->encrypt( $secret );
        $this->options->set( self::OPTION, $all, false );
    }

    public function get( string $name ): ?string {
        $all = $this->allEncrypted();
        if ( ! isset( $all[ $name ] ) || ! is_string( $all[ $name ] ) ) {
            return null;
        }

        return $this->decrypt( $all[ $name ] );
    }

    public function forget( string $name ): void {
        $all = $this->allEncrypted();
        unset( $all[ $name ] );
        $this->options->set( self::OPTION, $all, false );
    }

    public function has( string $name ): bool {
        return $this->get( $name ) !== null && $this->get( $name ) !== '';
    }

    /**
     * Masked view safe for admin JSON.
     *
     * @return array<string, string>
     */
    public function masked(): array {
        $masked = [];
        foreach ( array_keys( $this->allEncrypted() ) as $name ) {
            $masked[ (string) $name ] = $this->has( (string) $name ) ? '••••••••' : '';
        }

        return $masked;
    }

    /**
     * @return array<string, string>
     */
    private function allEncrypted(): array {
        $stored = $this->options->get( self::OPTION, [] );

        return is_array( $stored ) ? $stored : [];
    }

    private function encrypt( string $secret ): string {
        $key    = $this->key();
        $iv     = random_bytes( 12 );
        $tag    = '';
        $cipher = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
        if ( $cipher === false ) {
            throw new ConfigurationException( 'Credential encryption failed.' );
        }

        // Ciphertext encoding, not code obfuscation.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return base64_encode( $iv . $tag . $cipher );
    }

    private function decrypt( string $payload ): ?string {
        // Ciphertext encoding, not code obfuscation.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $raw = base64_decode( $payload, true );
        if ( $raw === false || strlen( $raw ) < 28 ) {
            return null;
        }
        $iv     = substr( $raw, 0, 12 );
        $tag    = substr( $raw, 12, 16 );
        $cipher = substr( $raw, 28 );
        $plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag );

        return $plain === false ? null : $plain;
    }

    private function key(): string {
        $salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'querynova-test-salt';

        return hash( 'sha256', $salt, true );
    }
}
