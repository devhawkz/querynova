<?php
/**
 * Cached sitemap documents. A generation counter drops stale pages after edits.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

use QueryNova\Infrastructure\Cache\CacheInterface;
use QueryNova\Modules\Sitemap\Domain\ContentCatalog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapRenderer {

    private const TTL = 900;

    /**
     * @param list<string> $enabledTypes
     */
    public function __construct(
        private readonly SitemapBuilder $builder,
        private readonly ContentCatalog $catalog,
        private readonly CacheInterface $cache,
        private readonly int $generation,
        private readonly array $enabledTypes,
        private readonly string $newsName,
        private readonly string $newsLanguage,
    ) {
    }

    public function index( string $baseUrl ): string {
        $cached = $this->cache->get( $this->key( 'index', 0 ) );
        if ( is_string( $cached ) ) {
            return $cached;
        }
        $xml = $this->builder->index( $this->catalog, $baseUrl, $this->enabledTypes );
        $this->cache->set( $this->key( 'index', 0 ), $xml, self::TTL );

        return $xml;
    }

    public function urlset( string $type, int $page, \DateTimeImmutable $now ): ?string {
        if ( preg_match( '/^[a-z0-9_-]+$/', $type ) !== 1 || ! in_array( $type, $this->enabledTypes, true ) ) {
            return null;
        }
        $page   = max( 1, $page );
        $cached = $this->cache->get( $this->key( $type, $page ) );
        if ( is_string( $cached ) ) {
            return $cached;
        }
        $xml = $this->builder->urlset( $this->catalog, $type, $page, $now, $this->newsName, $this->newsLanguage );
        $this->cache->set( $this->key( $type, $page ), $xml, self::TTL );

        return $xml;
    }

    private function key( string $type, int $page ): string {
        return 'sitemap:' . $this->generation . ':' . $type . ':' . $page;
    }
}
