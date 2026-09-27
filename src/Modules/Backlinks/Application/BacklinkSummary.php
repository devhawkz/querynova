<?php
/**
 * Counts only links the provider returned. The first snapshot cannot say which links are new.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks\Application;

use QueryNova\Modules\Backlinks\Domain\Backlink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BacklinkSummary {

    /**
     * @param list<Backlink>|null $links
     * @param list<string>|null   $previousSources Source URL hashes from the previous snapshot.
     * @return array<string, mixed>
     */
    public function summarize( ?array $links, ?array $previousSources, string $authorityMetric ): array {
        if ( $links === null ) {
            return [
                'status'            => 'unavailable',
                'backlinks'         => null,
                'referring_domains' => null,
                'dofollow'          => null,
                'nofollow'          => null,
                'new'               => null,
                'lost'              => null,
                'authority_metric'  => $authorityMetric,
            ];
        }
        $domains  = [];
        $dofollow = 0;
        $nofollow = 0;
        $current  = [];
        foreach ( $links as $link ) {
            $domain = $link->sourceDomain();
            if ( $domain !== '' ) {
                $domains[ $domain ] = true;
            }
            if ( $link->rel === 'dofollow' ) {
                ++$dofollow;
            } elseif ( $link->rel === 'nofollow' ) {
                ++$nofollow;
            }
            $current[ hash( 'sha256', $link->sourceUrl ) ] = true;
        }
        $new  = null;
        $lost = null;
        if ( $previousSources !== null ) {
            $new  = 0;
            $lost = 0;
            foreach ( array_keys( $current ) as $hash ) {
                if ( ! in_array( $hash, $previousSources, true ) ) {
                    ++$new;
                }
            }
            foreach ( $previousSources as $hash ) {
                if ( ! isset( $current[ $hash ] ) ) {
                    ++$lost;
                }
            }
        }

        return [
            'status'            => 'measured',
            'backlinks'         => count( $links ),
            'referring_domains' => count( $domains ),
            'dofollow'          => $dofollow,
            'nofollow'          => $nofollow,
            'new'               => $new,
            'lost'              => $lost,
            'authority_metric'  => $authorityMetric,
        ];
    }
}
