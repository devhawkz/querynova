<?php
/**
 * Reads on-page signals from one response. It does not fetch anything.
 *
 * A page under 100 words is later called thin. That threshold is QueryNova's rule, not a search-engine score.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

use QueryNova\Modules\Crawler\Domain\PageObservation;
use QueryNova\Modules\Crawler\Domain\SiteUrl;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HtmlInspector {

    public const THIN_WORDS = 100;

    /**
     * @param array<string, string> $headers
     */
    public function inspect( string $url, int $status, array $headers, string $html, int $depth ): PageObservation {
        $url         = SiteUrl::normalize( $url );
        $location    = $this->header( $headers, 'location' );
        $redirect    = in_array( $status, [ 301, 302, 303, 307, 308 ], true ) ? SiteUrl::resolve( $url, $location ) : '';
        $robots      = $this->header( $headers, 'x-robots-tag' );
        $dom         = $this->document( $html );
        $title       = '';
        $description = '';
        $canonical   = '';
        $h1          = [];
        $schema      = [];
        $hreflang    = [];
        $next        = '';
        $prev        = '';
        $links       = [];
        $text        = '';
        if ( $dom instanceof \DOMDocument ) {
            $title = $this->text( $dom->getElementsByTagName( 'title' )->item( 0 ) );
            foreach ( $dom->getElementsByTagName( 'h1' ) as $heading ) {
                $headingText = $this->text( $heading );
                if ( $headingText !== '' && count( $h1 ) < 3 ) {
                    $h1[] = $headingText;
                }
            }
            foreach ( $dom->getElementsByTagName( 'meta' ) as $meta ) {
                if ( ! $meta instanceof \DOMElement ) {
                    continue;
                }
                $name = strtolower( $meta->getAttribute( 'name' ) );
                if ( $name === 'description' ) {
                    $description = trim( $meta->getAttribute( 'content' ) );
                }
                if ( $name === 'robots' ) {
                    $robots = trim( $robots . ' ' . $meta->getAttribute( 'content' ) );
                }
            }
            foreach ( $dom->getElementsByTagName( 'link' ) as $link ) {
                if ( ! $link instanceof \DOMElement ) {
                    continue;
                }
                $rel  = strtolower( $link->getAttribute( 'rel' ) );
                $href = SiteUrl::resolve( $url, $link->getAttribute( 'href' ) );
                if ( str_contains( $rel, 'canonical' ) && $canonical === '' ) {
                    $canonical = $href;
                }
                if ( str_contains( $rel, 'alternate' ) && $link->getAttribute( 'hreflang' ) !== '' && $href !== '' && count( $hreflang ) < 20 ) {
                    $hreflang[] = [
                        'lang' => strtolower( trim( $link->getAttribute( 'hreflang' ) ) ),
                        'href' => $href,
                    ];
                }
                if ( str_contains( $rel, 'next' ) && $next === '' ) {
                    $next = $href;
                }
                if ( str_contains( $rel, 'prev' ) && $prev === '' ) {
                    $prev = $href;
                }
            }
            foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
                if ( ! $anchor instanceof \DOMElement || count( $links ) >= 30 ) {
                    break;
                }
                $resolved = SiteUrl::resolve( $url, $anchor->getAttribute( 'href' ) );
                if ( $resolved !== '' && SiteUrl::sameHost( $url, $resolved ) && ! $this->isAsset( $resolved ) && ! in_array( $resolved, $links, true ) ) {
                    $links[] = $resolved;
                }
            }
            foreach ( $dom->getElementsByTagName( 'script' ) as $script ) {
                if ( $script instanceof \DOMElement && strtolower( $script->getAttribute( 'type' ) ) === 'application/ld+json' ) {
                    $schema = array_merge( $schema, $this->schemaTypes( $script->textContent ) );
                }
            }
            $text = $this->visibleText( $dom );
        }
        $words     = $this->words( $text );
        $indexable = $status === 200 && ! str_contains( strtolower( $robots ), 'noindex' );
        $hash      = $words >= 20 ? hash( 'sha256', strtolower( $text ) ) : '';

        return new PageObservation(
            $url,
            $status,
            $depth,
            $redirect,
            $title,
            $description,
            $canonical,
            trim( $robots ),
            $indexable,
            $h1,
            $words,
            str_starts_with( $url, 'https://' ),
            array_values( array_unique( $schema ) ),
            $hreflang,
            $next,
            $prev,
            $links,
            $hash
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function header( array $headers, string $name ): string {
        return trim( $headers[ strtolower( $name ) ] ?? '' );
    }

    private function document( string $html ): ?\DOMDocument {
        $probe = strtolower( $html );
        if ( $html === '' || ( ! str_contains( $probe, '<html' ) && ! str_contains( $probe, '<!doctype' ) && ! str_contains( $probe, '<title' ) ) ) {
            return null;
        }
        $previous = libxml_use_internal_errors( true );
        $dom      = new \DOMDocument();
        $loaded   = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        return $loaded ? $dom : null;
    }

    private function text( ?\DOMNode $node ): string {
        if ( ! $node instanceof \DOMNode ) {
            return '';
        }

        return trim( preg_replace( '/\s+/', ' ', $node->textContent ) ?? '' );
    }

    private function visibleText( \DOMDocument $dom ): string {
        $xpath = new \DOMXPath( $dom );
        $nodes = $xpath->query( '//script|//style|//noscript' );
        if ( $nodes instanceof \DOMNodeList ) {
            foreach ( $nodes as $node ) {
                if ( $node instanceof \DOMNode && $node->parentNode instanceof \DOMNode ) {
                    $node->parentNode->removeChild( $node );
                }
            }
        }
        $text = $this->text( $dom->getElementsByTagName( 'body' )->item( 0 ) );

        return substr( $text, 0, 20000 );
    }

    private function words( string $text ): int {
        $text = trim( $text );
        if ( $text === '' ) {
            return 0;
        }

        $parts = preg_split( '/\s+/', $text );

        return is_array( $parts ) ? count( $parts ) : 0;
    }

    /**
     * @return list<string>
     */
    private function schemaTypes( string $json ): array {
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }
        $types = [];
        $this->collectTypes( $decoded, $types );

        return $types;
    }

    /**
     * @param array<mixed> $node
     * @param list<string> $types
     */
    private function collectTypes( array $node, array &$types ): void {
        if ( isset( $node['@type'] ) ) {
            $type = $node['@type'];
            if ( is_string( $type ) ) {
                $types[] = $type;
            } elseif ( is_array( $type ) ) {
                foreach ( $type as $item ) {
                    if ( is_string( $item ) ) {
                        $types[] = $item;
                    }
                }
            }
        }
        foreach ( $node as $value ) {
            if ( is_array( $value ) ) {
                $this->collectTypes( $value, $types );
            }
        }
    }

    private function isAsset( string $url ): bool {
        $path = strtolower( SiteUrl::path( $url ) );

        return preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|css|js|mp4|woff2?)$/', $path ) === 1;
    }
}
