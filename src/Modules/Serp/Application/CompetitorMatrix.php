<?php
/**
 * Compares our result with the top of a stored snapshot.
 *
 * Authority, backlinks, content, information gain, schema, E-E-A-T, and entities
 * stay null unless a provider supplied them. This class does not invent them.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Application;

use QueryNova\Modules\Serp\Domain\SerpHit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CompetitorMatrix {

    /**
     * @param list<SerpHit> $hits
     * @return array<string, mixed>
     */
    public function compare( array $hits, string $ourDomain ): array {
        $ours     = $this->ours( $hits, $ourDomain );
        $top3     = array_slice( $hits, 0, 3 );
        $top10    = array_slice( $hits, 0, 10 );
        $ourType  = $ours instanceof SerpHit ? $ours->pageType : '';
        $dominant = $this->dominantType( $top10 );

        return [
            'our_position'     => $ours instanceof SerpHit ? $ours->position : null,
            'our_url'          => $ours instanceof SerpHit ? $ours->url : '',
            'top3_median'      => $this->median( $top3 ),
            'top10_median'     => $this->median( $top10 ),
            'intent_match'     => $ourType !== '' && $ourType !== 'unknown' && $dominant !== '' ? $ourType === $dominant : null,
            'authority'        => null,
            'backlinks'        => null,
            'content'          => null,
            'information_gain' => null,
            'internal_links'   => null,
            'schema'           => null,
            'eeat'             => null,
            'entities'         => null,
            'note'             => 'Rank medians come from the stored snapshot. Authority, backlinks, content, information gain, schema, E-E-A-T, and entities were not supplied.',
        ];
    }

    /**
     * @param list<SerpHit> $hits
     * @return list<array<string, mixed>>
     */
    public function competitors( array $hits, string $ourDomain ): array {
        $rows   = [];
        $seen   = [];
        $domain = $this->normalize( $ourDomain );
        foreach ( $hits as $hit ) {
            $host = $this->normalize( $hit->domain );
            if ( $host === '' || $host === $domain || isset( $seen[ $host ] ) ) {
                continue;
            }
            $seen[ $host ] = true;
            $rows[]        = [
                'domain'            => $host,
                'rank'              => $hit->position,
                'url'               => $hit->url,
                'page_type'         => $hit->pageType,
                'authority'         => null,
                'referring_domains' => null,
                'backlinks'         => null,
                'traffic'           => null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<SerpHit> $hits
     * @return array{position: int|null, url: string}
     */
    public function oursPosition( array $hits, string $ourDomain ): array {
        $hit = $this->ours( $hits, $ourDomain );

        return [
            'position' => $hit instanceof SerpHit ? $hit->position : null,
            'url'      => $hit instanceof SerpHit ? $hit->url : '',
        ];
    }

    /**
     * @param list<SerpHit> $hits
     */
    private function ours( array $hits, string $ourDomain ): ?SerpHit {
        $domain = $this->normalize( $ourDomain );
        if ( $domain === '' ) {
            return null;
        }
        foreach ( $hits as $hit ) {
            if ( $this->normalize( $hit->domain ) === $domain ) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * @param list<SerpHit> $hits
     */
    private function median( array $hits ): ?float {
        if ( $hits === [] ) {
            return null;
        }
        $positions = [];
        foreach ( $hits as $hit ) {
            $positions[] = $hit->position;
        }
        sort( $positions );
        $count  = count( $positions );
        $middle = intdiv( $count, 2 );
        if ( $count % 2 === 1 ) {
            return (float) $positions[ $middle ];
        }

        return ( $positions[ $middle - 1 ] + $positions[ $middle ] ) / 2;
    }

    /**
     * @param list<SerpHit> $hits
     */
    private function dominantType( array $hits ): string {
        $counts = [];
        foreach ( $hits as $hit ) {
            if ( $hit->pageType === '' || $hit->pageType === 'unknown' ) {
                continue;
            }
            $counts[ $hit->pageType ] = ( $counts[ $hit->pageType ] ?? 0 ) + 1;
        }
        $best  = '';
        $score = 0;
        foreach ( $counts as $type => $count ) {
            if ( $count > $score ) {
                $best  = (string) $type;
                $score = $count;
            }
        }

        return $best;
    }

    private function normalize( string $domain ): string {
        $domain = strtolower( trim( $domain ) );
        $domain = preg_replace( '/^www\./', '', $domain );

        return is_string( $domain ) ? $domain : '';
    }
}
