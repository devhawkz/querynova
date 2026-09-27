<?php
/**
 * Classifies a URL the provider already returned. It does not fetch the page.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PageTypeClassifier {

    public function classify( string $url, string $title ): string {
        $host  = strtolower( (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?? '' ) );
        $path  = strtolower( (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '/' ) );
        $label = strtolower( $title . ' ' . $path );
        if ( $this->hostContains( $host, [ 'amazon.', 'ebay.', 'etsy.', 'walmart.', 'aliexpress.' ] ) ) {
            return 'marketplace';
        }
        if ( $this->hostContains( $host, [ 'youtube.', 'youtu.be', 'vimeo.' ] ) || str_contains( $path, '/watch' ) ) {
            return 'video';
        }
        if ( $this->hostContains( $host, [ 'reddit.', 'discourse.' ] ) || str_contains( $path, '/forum' ) ) {
            return 'forum';
        }
        if ( str_contains( $label, ' vs ' ) || str_contains( $path, '-vs-' ) || str_contains( $path, '/compare' ) ) {
            return 'comparison';
        }
        if ( $path === '/' || $path === '' ) {
            return 'homepage';
        }
        if ( str_contains( $host, 'maps.google' ) || str_contains( $path, '/location' ) ) {
            return 'local';
        }
        if ( str_contains( $path, '/brand' ) ) {
            return 'brand';
        }
        if ( str_contains( $path, '/product-category' ) || str_contains( $path, '/category' ) || str_contains( $path, '/collection' ) ) {
            return 'category';
        }
        if ( str_contains( $path, '/product' ) || str_contains( $path, '/shop/' ) || str_contains( $path, '/dp/' ) ) {
            return 'product';
        }
        if ( str_contains( $path, '/blog' ) || str_contains( $path, '/article' ) || str_contains( $path, '/news' ) || str_contains( $path, '/guide' ) ) {
            return 'article';
        }

        return 'unknown';
    }

    /**
     * @param list<string> $needles
     */
    private function hostContains( string $host, array $needles ): bool {
        foreach ( $needles as $needle ) {
            if ( str_contains( $host, $needle ) ) {
                return true;
            }
        }

        return false;
    }
}
