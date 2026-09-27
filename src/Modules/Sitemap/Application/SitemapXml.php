<?php
/**
 * Sitemap XML. Values are escaped for XML.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

use QueryNova\Modules\Sitemap\Domain\SitemapCandidate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapXml {

    /**
     * @param list<array{loc: string, lastmod: string}> $sitemaps
     */
    public function index( array $sitemaps ): string {
        $body = '';
        foreach ( $sitemaps as $sitemap ) {
            $body .= '<sitemap><loc>' . $this->escape( $sitemap['loc'] ) . '</loc>';
            if ( $sitemap['lastmod'] !== '' ) {
                $body .= '<lastmod>' . $this->escape( $sitemap['lastmod'] ) . '</lastmod>';
            }
            $body .= '</sitemap>';
        }

        return $this->document(
            'sitemapindex',
            'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"',
            $body
        );
    }

    /**
     * @param list<SitemapCandidate> $entries
     */
    public function urlset( array $entries, string $type, string $newsName, string $newsLanguage ): string {
        $namespaces = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ( $type === 'image' || $this->anyImages( $entries ) ) {
            $namespaces .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        if ( $type === 'video' || $this->anyVideos( $entries ) ) {
            $namespaces .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
        }
        if ( $type === 'news' ) {
            $namespaces .= ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"';
        }

        $body = '';
        foreach ( $entries as $entry ) {
            $body .= '<url><loc>' . $this->escape( $entry->url() ) . '</loc>';
            if ( $entry->lastModified() !== '' ) {
                $body .= '<lastmod>' . $this->escape( $entry->lastModified() ) . '</lastmod>';
            }
            if ( $type === 'image' || $type === 'post' || $type === 'page' || $type === 'product' ) {
                foreach ( $entry->images() as $image ) {
                    $body .= '<image:image><image:loc>' . $this->escape( $image ) . '</image:loc></image:image>';
                }
            }
            if ( $type === 'video' ) {
                foreach ( $entry->videos() as $video ) {
                    $body .= '<video:video>';
                    if ( $video['thumbnail'] !== '' ) {
                        $body .= '<video:thumbnail_loc>' . $this->escape( $video['thumbnail'] ) . '</video:thumbnail_loc>';
                    }
                    $body .= '<video:title>' . $this->escape( $video['title'] ) . '</video:title>';
                    $body .= '<video:description>' . $this->escape( $video['title'] ) . '</video:description>';
                    $body .= '<video:content_loc>' . $this->escape( $video['url'] ) . '</video:content_loc>';
                    $body .= '</video:video>';
                }
            }
            if ( $type === 'news' ) {
                $body .= '<news:news><news:publication><news:name>' . $this->escape( $newsName ) . '</news:name>';
                $body .= '<news:language>' . $this->escape( $newsLanguage ) . '</news:language></news:publication>';
                $body .= '<news:publication_date>' . $this->escape( $entry->newsPublishedAt() ) . '</news:publication_date>';
                $body .= '<news:title>' . $this->escape( $entry->newsTitle() ) . '</news:title></news:news>';
            }
            $body .= '</url>';
        }

        return $this->document( 'urlset', $namespaces, $body );
    }

    private function document( string $root, string $namespaces, string $body ): string {
        return '<?xml version="1.0" encoding="UTF-8"?><' . $root . ' ' . $namespaces . '>' . $body . '</' . $root . '>';
    }

    /**
     * @param list<SitemapCandidate> $entries
     */
    private function anyImages( array $entries ): bool {
        foreach ( $entries as $entry ) {
            if ( $entry->images() !== [] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SitemapCandidate> $entries
     */
    private function anyVideos( array $entries ): bool {
        foreach ( $entries as $entry ) {
            if ( $entry->videos() !== [] ) {
                return true;
            }
        }

        return false;
    }

    private function escape( string $value ): string {
        return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }
}
