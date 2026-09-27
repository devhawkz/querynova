<?php
/**
 * Appends the sitemap index URL to robots.txt output.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RobotsSitemapLine {

    public function append( string $robots, string $sitemapUrl, bool $isPublic, bool $enabled ): string {
        if ( ! $isPublic || ! $enabled || $sitemapUrl === '' ) {
            return $robots;
        }

        return rtrim( $robots ) . "\nSitemap: " . $sitemapUrl . "\n";
    }
}
