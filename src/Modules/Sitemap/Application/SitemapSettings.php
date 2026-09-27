<?php
/**
 * Sitemap options. News stays off until a publication name is stored.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapSettings {

    public const NEWS_NAME     = 'querynova_sitemap_news_name';
    public const NEWS_LANGUAGE = 'querynova_sitemap_news_language';
    public const GENERATION    = 'querynova_sitemap_generation';

    public function newsName(): string {
        $name = get_option( self::NEWS_NAME, '' );

        return is_string( $name ) ? trim( wp_strip_all_tags( $name ) ) : '';
    }

    public function newsLanguage(): string {
        $language = get_option( self::NEWS_LANGUAGE, '' );
        if ( ! is_string( $language ) || ! $this->isLanguageTag( $language ) ) {
            $language = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'language' ) : 'en';
        }
        if ( ! $this->isLanguageTag( $language ) ) {
            return 'en';
        }

        return $language;
    }

    public function generation(): int {
        return max( 0, (int) get_option( self::GENERATION, 0 ) );
    }

    public function bumpGeneration(): void {
        update_option( self::GENERATION, (string) ( $this->generation() + 1 ), false );
    }

    private function isLanguageTag( string $language ): bool {
        return preg_match( '/^[a-z]{2}(?:-[A-Za-z0-9]+)?$/', $language ) === 1;
    }
}
