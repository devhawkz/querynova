<?php
/**
 * Pulls image and video URLs out of post HTML.
 *
 * Only http and https URLs are returned. The extractor does not fetch them.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MediaExtractor {

    /**
     * @return list<string>
     */
    public function images( string $html ): array {
        if ( $html === '' || ! preg_match_all( '/<img\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches ) ) {
            return [];
        }

        return $this->uniqueHttpUrls( $matches[2], 50 );
    }

    /**
     * @return list<array{url: string, title: string, thumbnail: string}>
     */
    public function videos( string $html, string $title ): array {
        $urls = [];
        if ( preg_match_all( '/<(?:video|source)\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches ) ) {
            $urls = $matches[2];
        }
        if ( preg_match_all( '#https?://(?:www\.)?(?:youtube\.com/watch\?v=[\w-]+|youtu\.be/[\w-]+|vimeo\.com/\d+)#i', $html, $links ) ) {
            $urls = array_merge( $urls, $links[0] );
        }

        $videos = [];
        foreach ( $this->uniqueHttpUrls( $urls, 10 ) as $url ) {
            $videos[] = [
                'url'       => $url,
                'title'     => $title,
                'thumbnail' => '',
            ];
        }

        return $videos;
    }

    /**
     * @param list<string> $urls
     * @return list<string>
     */
    private function uniqueHttpUrls( array $urls, int $limit ): array {
        $clean = [];
        foreach ( $urls as $url ) {
            $url = trim( $url );
            if ( ! $this->isHttp( $url ) || in_array( $url, $clean, true ) ) {
                continue;
            }
            $clean[] = $url;
            if ( count( $clean ) >= $limit ) {
                break;
            }
        }

        return $clean;
    }

    private function isHttp( string $url ): bool {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return false;
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );

        return in_array( $scheme, [ 'http', 'https' ], true ) && ( $parts['host'] ?? '' ) !== '';
    }
}
