<?php
/**
 * Invalidates cached analysis when the underlying entity changes.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CacheInvalidator {

    public function __construct( private readonly CacheInterface $cache ) {
    }

    public function productUpdated( int $productId ): void {
        $this->cache->delete( 'seo:product:' . $productId );
        $this->cache->delete( 'analysis:product:' . $productId );
        $this->cache->delete( 'schema:product:' . $productId );
    }

    public function pageUpdated( int $postId ): void {
        $this->cache->delete( 'seo:post:' . $postId );
        $this->cache->delete( 'analysis:post:' . $postId );
        $this->cache->delete( 'sitemap:index' );
    }
}
