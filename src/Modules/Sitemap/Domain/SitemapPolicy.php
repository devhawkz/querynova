<?php
/**
 * Decides whether a URL belongs in a sitemap.
 *
 * Inclusion requires a public published URL, indexability, and a canonical that
 * is empty or points at the same URL. News is limited to the last 48 hours.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapPolicy {

    public function __construct( private readonly string $newsPublicationName = '' ) {
    }

    public function allows( SitemapCandidate $candidate, string $sitemapType, \DateTimeImmutable $now ): bool {
        if ( $candidate->status() !== 'publish' || $candidate->visibility() !== 'public' || $candidate->passwordProtected() ) {
            return false;
        }
        if ( ! $candidate->indexable() || ! $this->isPublicHttpUrl( $candidate->url() ) ) {
            return false;
        }
        if ( ! $this->canonicalAllows( $candidate ) ) {
            return false;
        }
        if ( $sitemapType === 'image' ) {
            return $candidate->images() !== [];
        }
        if ( $sitemapType === 'video' ) {
            return $candidate->videos() !== [];
        }
        if ( $sitemapType === 'news' ) {
            return $this->newsAllows( $candidate, $now );
        }

        return $candidate->channel() === $sitemapType;
    }

    private function canonicalAllows( SitemapCandidate $candidate ): bool {
        $canonical = $candidate->canonical();
        if ( $canonical === '' ) {
            return true;
        }

        return $this->normalize( $canonical ) === $this->normalize( $candidate->url() );
    }

    private function newsAllows( SitemapCandidate $candidate, \DateTimeImmutable $now ): bool {
        if ( $this->newsPublicationName === '' || $candidate->newsTitle() === '' || $candidate->newsPublishedAt() === '' ) {
            return false;
        }
        try {
            $published = new \DateTimeImmutable( $candidate->newsPublishedAt() );
        } catch ( \Exception ) {
            return false;
        }
        $age = $now->getTimestamp() - $published->getTimestamp();

        return $age >= 0 && $age <= 172800;
    }

    private function isPublicHttpUrl( string $url ): bool {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return false;
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );

        return in_array( $scheme, [ 'http', 'https' ], true ) && ( $parts['host'] ?? '' ) !== '';
    }

    private function normalize( string $url ): string {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return strtolower( rtrim( $url, '/' ) );
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
        $host   = strtolower( (string) ( $parts['host'] ?? '' ) );
        $path   = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
        $query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }
}
