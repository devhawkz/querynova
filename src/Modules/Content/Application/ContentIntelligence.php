<?php
/**
 * Content, intent, entities, and internal-link notes from supplied text.
 *
 * This is not a Google E-E-A-T score, and it does not fetch the page.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Core\Domain\ProvenanceKind;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentIntelligence {

    /**
     * @param list<string> $pageTypes
     * @return array{primary: string, secondary: string|null, confidence: string, note: string}
     */
    public function intent( string $query, array $pageTypes = [] ): array {
        $text    = strtolower( trim( $query ) );
        $primary = 'unknown';
        if ( str_contains( $text, 'near me' ) || in_array( 'local', $pageTypes, true ) ) {
            $primary = 'local';
        } elseif ( str_contains( $text, 'login' ) || str_contains( $text, 'official' ) || in_array( 'homepage', $pageTypes, true ) ) {
            $primary = 'navigational';
		} elseif ( $this->contains( $text, [ 'best', 'review', ' vs ', 'comparison' ] ) || in_array( 'comparison', $pageTypes, true ) ) {
			$primary = 'commercial_investigation';
		} elseif ( $this->contains( $text, [ 'buy', 'price', 'order', 'shop' ] ) ) {
			$primary = 'transactional';
		} elseif ( $this->contains( $text, [ 'how ', 'what ', 'why ', 'guide' ] ) || in_array( 'article', $pageTypes, true ) ) {
            $primary = 'informational';
        }
        $secondary = null;
        if ( $primary === 'commercial_investigation' && $this->contains( $text, [ 'price', 'buy' ] ) ) {
            $secondary = 'transactional';
        }
        $confidence = ConfidenceBand::Unknown;
        if ( $primary !== 'unknown' ) {
            $confidence = $pageTypes === [] ? ConfidenceBand::Low : ConfidenceBand::Medium;
        }

        return [
            'primary'    => $primary,
            'secondary'  => $secondary,
            'confidence' => $confidence->value,
            'note'       => 'QueryNova intent label from the query and supplied page types. Not a Google classification.',
        ];
    }

    /**
     * Observations from supplied HTML. Scores for completeness, originality, expertise, and freshness stay null.
     *
     * @param list<string> $topics
     * @return array<string, mixed>
     */
    public function analyze( string $html, string $keyword, array $topics ): array {
        if ( trim( $html ) === '' ) {
            return [
                'status'          => ProvenanceKind::Unavailable->value,
                'word_count'      => null,
                'structure'       => null,
                'topics_covered'  => null,
                'topics_total'    => null,
                'completeness'    => null,
                'keyword_density' => null,
                'examples'        => null,
                'tables'          => null,
                'citations'       => null,
                'first_party'     => null,
                'specificity'     => null,
                'originality'     => null,
                'expertise'       => null,
                'freshness'       => null,
                'note'            => 'No document was supplied, so completeness and density are unavailable.',
            ];
        }
        $text  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ?? '' );
        $parts = preg_split( '/\s+/', $text );
        $words = $text === '' ? 0 : ( is_array( $parts ) ? count( $parts ) : 0 );
        $lower = strtolower( $text );
        $raw   = strtolower( $html );
        $hits  = [];
        foreach ( $topics as $topic ) {
            if ( $topic !== '' && str_contains( $lower, strtolower( $topic ) ) ) {
                $hits[] = $topic;
            }
        }
        $density = null;
        if ( $keyword !== '' && $words > 0 ) {
            $found   = substr_count( $lower, strtolower( $keyword ) );
            $density = [
                'value'      => round( $found / $words, 4 ),
                'provenance' => ProvenanceKind::Measured->value,
                'note'       => 'Keyword density is a secondary observation, not a ranking score.',
            ];
        }

        return [
            'status'          => ProvenanceKind::Measured->value,
            'word_count'      => $words,
            'structure'       => [
                'headings' => substr_count( $raw, '<h' ),
                'tables'   => str_contains( $raw, '<table' ),
            ],
            'topics_covered'  => $topics === [] ? null : $hits,
            'topics_total'    => $topics === [] ? null : count( $topics ),
            'completeness'    => null,
            'keyword_density' => $density,
            'examples'        => str_contains( $lower, 'for example' ),
            'tables'          => str_contains( $raw, '<table' ),
            'citations'       => str_contains( $raw, 'http://' ) || str_contains( $raw, 'https://' ) || str_contains( $lower, 'according to' ),
            'first_party'     => str_contains( $lower, 'we tested' ) || str_contains( $lower, 'we measured' ),
            'specificity'     => preg_match( '/\d+\s?(mm|cm|kg|a|v|w)\b/', $lower ) === 1,
            'originality'     => null,
            'expertise'       => null,
            'freshness'       => null,
            'note'            => 'Completeness, originality, expertise, and freshness are unavailable. Topic hits and density are observations, not a content score.',
        ];
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function informationGain( string $html ): array {
        $empty = [
            'status'            => ProvenanceKind::Unavailable->value,
            'original_research' => null,
            'tests'             => null,
            'measurements'      => null,
            'case_studies'      => null,
            'tables'            => null,
            'photos'            => null,
            'videos'            => null,
            'quotes'            => null,
            'tools'             => null,
            'calculators'       => null,
            'datasets'          => null,
            'comparisons'       => null,
        ];
        if ( trim( $html ) === '' ) {
            return $empty;
        }
        $text = strtolower( $html );

        return [
            'status'            => ProvenanceKind::Measured->value,
            'original_research' => str_contains( $text, 'original research' ),
            'tests'             => str_contains( $text, 'we tested' ),
            'measurements'      => preg_match( '/\d+\s?(mm|cm|kg|a|v|w)\b/', $text ) === 1 || str_contains( $text, 'we measured' ),
            'case_studies'      => str_contains( $text, 'case study' ),
            'tables'            => str_contains( $text, '<table' ),
            'photos'            => str_contains( $text, '<img' ),
            'videos'            => str_contains( $text, '<video' ) || str_contains( $text, 'youtube.com' ),
            'quotes'            => str_contains( $text, '<blockquote' ),
            'tools'             => str_contains( $text, 'tool' ),
            'calculators'       => str_contains( $text, 'calculator' ),
            'datasets'          => str_contains( $text, 'dataset' ),
            'comparisons'       => str_contains( $text, ' vs ' ),
        ];
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function productGain( string $html ): array {
        $empty = [
            'status'             => ProvenanceKind::Unavailable->value,
            'measurements'       => null,
            'compatibility'      => null,
            'selection_guides'   => null,
            'application_guides' => null,
            'datasheets'         => null,
            'cad'                => null,
            'manuals'            => null,
            'photos'             => null,
            'video'              => null,
        ];
        if ( trim( $html ) === '' ) {
            return $empty;
        }
        $text = strtolower( $html );

        return [
            'status'             => ProvenanceKind::Measured->value,
            'measurements'       => preg_match( '/\d+\s?(mm|cm|kg|a|v|w)\b/', $text ) === 1,
            'compatibility'      => str_contains( $text, 'compatib' ),
            'selection_guides'   => str_contains( $text, 'selection guide' ),
            'application_guides' => str_contains( $text, 'application guide' ),
            'datasheets'         => str_contains( $text, 'datasheet' ),
            'cad'                => str_contains( $text, 'cad' ),
            'manuals'            => str_contains( $text, 'manual' ),
            'photos'             => str_contains( $text, '<img' ),
            'video'              => str_contains( $text, '<video' ) || str_contains( $text, 'youtube.com' ),
        ];
    }

    /**
     * A topic in the supplied top 3 or top 10 is important. A topic only on our page is optional.
     * Without SERP text the classification stays null.
     *
     * @param list<string> $topics
     * @param list<string> $topThree
     * @param list<string> $topTen
     * @return list<array{topic: string, classification: string|null, on_our_page: bool|null, on_top_3: bool|null, on_top_10: bool|null}>
     */
    public function coverage( string $html, array $topics, array $topThree, array $topTen ): array {
        $rows     = [];
        $haveSerp = $topThree !== [] || $topTen !== [];
        $ours     = strtolower( $html );
        foreach ( $topics as $topic ) {
            $needle  = strtolower( $topic );
            $onOurs  = $needle !== '' && str_contains( $ours, $needle );
            $onTop3  = $haveSerp ? $this->anyContains( $topThree, $needle ) : null;
            $onTop10 = $haveSerp ? $this->anyContains( $topTen, $needle ) : null;
            $class   = null;
            if ( $haveSerp && $needle !== '' ) {
                if ( $onTop3 === true || $onTop10 === true ) {
                    $class = 'important';
                } elseif ( $onOurs ) {
                    $class = 'optional';
                } else {
                    $class = 'irrelevant';
                }
            }
            $rows[] = [
                'topic'          => $topic,
                'classification' => $class,
                'on_our_page'    => trim( $html ) === '' ? null : $onOurs,
                'on_top_3'       => $onTop3,
                'on_top_10'      => $onTop10,
            ];
        }

        return $rows;
    }

    /**
     * Important topics that are missing from the supplied document. This does not crawl.
     *
     * @param list<array{topic: string, classification: string|null, on_our_page: bool|null, on_top_3: bool|null, on_top_10: bool|null}> $coverage
     * @return array{status: string, topics: list<string>|null, note: string}
     */
    public function gap( array $coverage ): array {
        if ( $coverage === [] ) {
            return [
                'status' => ProvenanceKind::Unavailable->value,
                'topics' => null,
                'note'   => 'A gap needs supplied topics. QueryNova did not crawl.',
            ];
        }
        $measured = false;
        $missing  = [];
        foreach ( $coverage as $row ) {
            if ( $row['classification'] === null ) {
                continue;
            }
            $measured = true;
            if ( $row['classification'] === 'important' && $row['on_our_page'] === false ) {
                $missing[] = $row['topic'];
            }
        }
        if ( ! $measured ) {
            return [
                'status' => ProvenanceKind::Unavailable->value,
                'topics' => null,
                'note'   => 'Gap needs supplied top results. QueryNova did not crawl.',
            ];
        }

        return [
            'status' => ProvenanceKind::Measured->value,
            'topics' => $missing,
            'note'   => 'Topics marked important that are absent from the supplied document. This is not a score.',
        ];
    }

    /**
     * Evidence checklist. There is no combined E-E-A-T score.
     *
     * @param array<string, bool|null> $supplied
     * @return array<string, mixed>
     */
    public function evidence( string $html, array $supplied ): array {
        $text  = strtolower( $html );
        $empty = trim( $html ) === '';

        return [
            'label'        => 'E-E-A-T evidence',
            'score'        => null,
            'note'         => 'This is not a Google E-E-A-T score.',
            'experience'   => $supplied['experience'] ?? ( $empty ? null : str_contains( $text, 'we tested' ) ),
            'expertise'    => $supplied['expertise'] ?? null,
            'author'       => $supplied['author'] ?? null,
            'trust'        => $supplied['trust'] ?? null,
            'warranty'     => $empty ? null : str_contains( $text, 'warranty' ),
            'returns'      => $empty ? null : str_contains( $text, 'return' ),
            'support'      => $empty ? null : str_contains( $text, 'support' ),
            'company'      => $supplied['company'] ?? null,
            'technical'    => $supplied['technical'] ?? null,
            'manufacturer' => $supplied['manufacturer'] ?? null,
            'contact'      => $empty ? null : ( str_contains( $text, 'contact' ) || str_contains( $text, '@' ) ),
        ];
    }

    /**
     * Mentions and co-mention edges from a supplied dictionary. An empty dictionary is unavailable.
     *
     * @param list<array{name: string, type: string}> $known
     * @return array{status: string, mentions: list<array{name: string, type: string, relation: string}>, relationships: list<array{from: string, to: string, relation: string}>|null}
     */
    public function entities( string $html, array $known ): array {
        if ( $known === [] ) {
            return [
                'status'        => ProvenanceKind::Unavailable->value,
                'mentions'      => [],
                'relationships' => null,
            ];
        }
        $mentions = [];
        $text     = strtolower( $html );
        foreach ( $known as $entity ) {
            $name = trim( $entity['name'] );
            $type = trim( $entity['type'] );
            if ( $name !== '' && $type !== '' && str_contains( $text, strtolower( $name ) ) ) {
                $mentions[] = [
                    'name'     => $name,
                    'type'     => $type,
                    'relation' => 'mentions',
                ];
            }
        }
        $relationships = [];
        $count         = count( $mentions );
        for ( $left = 0; $left < $count; $left++ ) {
            for ( $right = $left + 1; $right < $count; $right++ ) {
                $relationships[] = [
                    'from'     => $mentions[ $left ]['name'],
                    'to'       => $mentions[ $right ]['name'],
                    'relation' => 'co_mentioned',
                ];
            }
        }

        return [
            'status'        => ProvenanceKind::Measured->value,
            'mentions'      => $mentions,
            'relationships' => $relationships,
        ];
    }

    /**
     * Topic map from supplied pages. Rank and backlink counts stay null when they were not supplied.
     *
     * @param list<array{topic: string, subtopics?: list<string>, pages?: list<string>, products?: list<string>, categories?: list<string>, articles?: list<string>, internal_links?: int|null, rank?: float|null, backlinks?: int|null}> $topics
     * @return array<string, mixed>
     */
    public function topicalAuthority( array $topics ): array {
        if ( $topics === [] ) {
            return [
                'status' => ProvenanceKind::Unavailable->value,
                'topics' => null,
                'note'   => 'Topical coverage is unavailable until pages are supplied. This is not an authority score.',
            ];
        }
        $rows = [];
        foreach ( $topics as $topic ) {
            $rows[] = [
                'topic'          => $topic['topic'],
                'subtopics'      => $topic['subtopics'] ?? [],
                'pages'          => $topic['pages'] ?? [],
                'products'       => $topic['products'] ?? [],
                'categories'     => $topic['categories'] ?? [],
                'articles'       => $topic['articles'] ?? [],
                'internal_links' => $topic['internal_links'] ?? null,
                'rank'           => $topic['rank'] ?? null,
                'backlinks'      => $topic['backlinks'] ?? null,
            ];
        }

        return [
            'status' => ProvenanceKind::Measured->value,
            'topics' => $rows,
            'note'   => 'This map lists supplied coverage. Missing rank or backlinks stay null. It is not an authority score.',
        ];
    }

    /**
     * Link graph from supplied edges. Depth, broken links, and anchors stay null when omitted.
     *
     * @param list<array{url: string, links: list<string>, depth?: int|null, anchors?: list<string>, broken?: list<string>}> $pages
     * @return array<string, mixed>
     */
    public function linkGraph( array $pages, string $origin ): array {
        if ( $pages === [] ) {
            return [
                'status'   => ProvenanceKind::Unavailable->value,
                'pages'    => null,
                'incoming' => null,
                'orphans'  => null,
                'broken'   => null,
                'anchors'  => null,
            ];
        }
        $incoming = [];
        $known    = [];
        $broken   = null;
        $anchors  = null;
        foreach ( $pages as $page ) {
            $known[ $page['url'] ]    = true;
            $incoming[ $page['url'] ] = $incoming[ $page['url'] ] ?? 0;
            foreach ( $page['links'] as $link ) {
                $incoming[ $link ] = ( $incoming[ $link ] ?? 0 ) + 1;
            }
            if ( array_key_exists( 'broken', $page ) ) {
                $broken = $broken ?? [];
                foreach ( $page['broken'] as $link ) {
                    $broken[] = $link;
                }
            }
            if ( array_key_exists( 'anchors', $page ) ) {
                $anchors = $anchors ?? [];
                foreach ( $page['anchors'] as $anchor ) {
                    $key             = $anchor === '' ? '(empty)' : $anchor;
                    $anchors[ $key ] = ( $anchors[ $key ] ?? 0 ) + 1;
                }
            }
        }
        $orphans = [];
        foreach ( array_keys( $known ) as $url ) {
            if ( $url !== $origin && ( $incoming[ $url ] ?? 0 ) === 0 ) {
                $orphans[] = $url;
            }
        }
        $rows = [];
        foreach ( $pages as $page ) {
            $rows[] = [
                'url'      => $page['url'],
                'outgoing' => count( $page['links'] ),
                'incoming' => $incoming[ $page['url'] ] ?? 0,
                'depth'    => array_key_exists( 'depth', $page ) ? $page['depth'] : null,
            ];
        }

        return [
            'status'  => ProvenanceKind::Measured->value,
            'pages'   => $rows,
            'orphans' => $orphans,
            'broken'  => $broken,
            'anchors' => $anchors,
        ];
    }

    /**
     * Suggestions only. Nothing is inserted into content.
     *
     * @param list<array{type: string, name: string, url: string}> $items
     * @return list<array{from: string, to: string, reason: string, applied: bool}>
     */
    public function commerceLinks( array $items ): array {
        $suggestions = [];
        foreach ( $items as $from ) {
            $token = $this->token( $from['name'] );
            if ( $token === '' ) {
                continue;
            }
            foreach ( $items as $to ) {
                if ( $from['url'] === $to['url'] || ! str_contains( strtolower( $to['name'] ), $token ) ) {
                    continue;
                }
                $reason = $this->linkReason( $from['type'], $to['type'], $to['name'] );
                if ( $reason === '' ) {
                    continue;
                }
                $suggestions[] = [
                    'from'    => $from['url'],
                    'to'      => $to['url'],
                    'reason'  => $reason,
                    'applied' => false,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * @param list<string> $needles
     */
    private function contains( string $haystack, array $needles ): bool {
        foreach ( $needles as $needle ) {
            if ( str_contains( $haystack, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $rows
     */
    private function anyContains( array $rows, string $needle ): bool {
        if ( $needle === '' ) {
            return false;
        }
        foreach ( $rows as $row ) {
            if ( str_contains( strtolower( $row ), $needle ) ) {
                return true;
            }
        }

        return false;
    }

    private function token( string $name ): string {
        $parts = preg_split( '/[^a-z0-9]+/i', strtolower( $name ) );
        if ( ! is_array( $parts ) ) {
            return '';
        }
        foreach ( $parts as $part ) {
            if ( strlen( $part ) >= 4 ) {
                return $part;
            }
        }

        return '';
    }

    private function linkReason( string $from, string $to, string $targetName ): string {
        $article = $from === 'article' || $from === 'blog';
        if ( $article && $to === 'category' ) {
            return 'article_to_category';
        }
        if ( $article && $to === 'product' ) {
            return 'article_to_product';
        }
        if ( $from === 'product' && $to === 'category' ) {
            return 'product_to_category';
        }
        if ( $from === 'category' && ( $to === 'article' || $to === 'blog' ) ) {
            return 'category_to_guide';
        }
        if ( $from === 'product' && $to === 'product' && str_contains( strtolower( $targetName ), 'accessor' ) ) {
            return 'product_to_accessory';
        }
        if ( $from === 'brand' && $to === 'category' ) {
            return 'brand_to_category';
        }

        return '';
    }
}
