<?php
/**
 * Paged content source for sitemap channels.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ContentCatalog {

    /**
     * @return list<string>
     */
    public function types(): array;

    public function count( string $type ): int;

    /**
     * @return list<SitemapCandidate>
     */
    public function page( string $type, int $page, int $perPage ): array;
}
