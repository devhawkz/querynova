<?php
/**
 * Language context for SEO analysis.
 *
 * WPML and Polylang are read through their public APIs when those plugins
 * are loaded. This class does not load either plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LanguageResolver {

    public function __construct(
        private readonly bool $wpml = false,
        private readonly ?string $wpmlLanguage = null,
        private readonly bool $polylang = false,
        private readonly ?string $polylangLanguage = null,
        private readonly ?string $locale = null,
    ) {
    }

    public static function fromWordPress(): self {
        $wpml         = function_exists( 'has_filter' ) && has_filter( 'wpml_current_language' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- this is WPML's public hook, not a QueryNova hook.
        $wpmlLanguage = null;
        if ( $wpml ) {
            $value        = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- this is WPML's public hook, not a QueryNova hook.
            $wpmlLanguage = is_string( $value ) && $value !== '' ? $value : null;
        }
        $polylang         = function_exists( 'pll_current_language' );
        $polylangLanguage = null;
        if ( $polylang ) {
            $value            = pll_current_language( 'slug' );
            $polylangLanguage = is_string( $value ) && $value !== '' ? $value : null;
        }
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : null;

        return new self(
            $wpml,
            $wpmlLanguage,
            $polylang,
            $polylangLanguage,
            is_string( $locale ) && $locale !== '' ? $locale : null
        );
    }

    /**
     * @return array{provider: string, language: string|null}
     */
    public function context( ?string $requested = null ): array {
        $requested = is_string( $requested ) ? trim( $requested ) : '';
        if ( $requested !== '' ) {
            return [
                'provider' => 'request',
                'language' => $requested,
            ];
        }
        if ( $this->wpml ) {
            return [
                'provider' => 'wpml',
                'language' => $this->wpmlLanguage,
            ];
        }
        if ( $this->polylang ) {
            return [
                'provider' => 'polylang',
                'language' => $this->polylangLanguage,
            ];
        }

        return [
            'provider' => 'site',
            'language' => $this->locale,
        ];
    }
}
