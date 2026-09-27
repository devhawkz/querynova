<?php
/**
 * Builds sitemap indexes and paged URL sets without loading the whole catalog.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

use QueryNova\Modules\Sitemap\Domain\ContentCatalog;
use QueryNova\Modules\Sitemap\Domain\SitemapPolicy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapBuilder {

    public function __construct(
        private readonly SitemapPolicy $policy,
        private readonly SitemapXml $xml,
        private readonly int $perPage = 1000,
    ) {
    }

    public function perPage(): int {
        return $this->perPage;
    }

    /**
     * @param list<string> $enabledTypes
     */
    public function index( ContentCatalog $catalog, string $baseUrl, array $enabledTypes ): string {
        $sitemaps = [];
        $base     = rtrim( $baseUrl, '/' ) . '/';
        foreach ( $catalog->types() as $type ) {
            if ( ! in_array( $type, $enabledTypes, true ) ) {
                continue;
            }
            $count = $catalog->count( $type );
            if ( $count < 1 ) {
                continue;
            }
            $pages = (int) ceil( $count / $this->perPage );
            for ( $page = 1; $page <= $pages; $page++ ) {
                $sitemaps[] = [
                    'loc'     => $base . 'querynova-sitemap-' . rawurlencode( $type ) . '-' . $page . '.xml',
                    'lastmod' => '',
                ];
            }
        }

        return $this->xml->index( $sitemaps );
    }

    public function urlset( ContentCatalog $catalog, string $type, int $page, \DateTimeImmutable $now, string $newsName, string $newsLanguage ): string {
        $page     = max( 1, $page );
        $included = [];
        foreach ( $catalog->page( $type, $page, $this->perPage ) as $candidate ) {
            if ( $this->policy->allows( $candidate, $type, $now ) ) {
                $included[] = $candidate;
            }
        }

        return $this->xml->urlset( $included, $type, $newsName, $newsLanguage );
    }
}
