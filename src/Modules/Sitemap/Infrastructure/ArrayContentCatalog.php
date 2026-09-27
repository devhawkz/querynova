<?php
/**
 * In-memory catalog used by tests and by fixtures.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Infrastructure;

use QueryNova\Modules\Sitemap\Domain\ContentCatalog;
use QueryNova\Modules\Sitemap\Domain\SitemapCandidate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ArrayContentCatalog implements ContentCatalog {

    /**
     * @param list<SitemapCandidate> $candidates
     * @param list<string>           $types
     */
    public function __construct(
        private readonly array $candidates,
        private readonly array $types,
    ) {
    }

    public function types(): array {
        return $this->types;
    }

    public function count( string $type ): int {
        return count( $this->matching( $type ) );
    }

    public function page( string $type, int $page, int $perPage ): array {
        $matching = $this->matching( $type );
        $offset   = max( 0, ( $page - 1 ) * $perPage );

        return array_slice( $matching, $offset, $perPage );
    }

    /**
     * @return list<SitemapCandidate>
     */
    private function matching( string $type ): array {
        $matched = [];
        foreach ( $this->candidates as $candidate ) {
            if ( $type === 'image' && $candidate->images() !== [] ) {
                $matched[] = $candidate;
                continue;
            }
            if ( $type === 'video' && $candidate->videos() !== [] ) {
                $matched[] = $candidate;
                continue;
            }
            if ( $type === 'news' && $candidate->newsPublishedAt() !== '' ) {
                $matched[] = $candidate;
                continue;
            }
            if ( $candidate->channel() === $type ) {
                $matched[] = $candidate;
            }
        }

        return $matched;
    }
}
