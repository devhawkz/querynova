<?php
/**
 * Domains that link to competitors and not to us.
 *
 * Overlap is the number of competitors linked. Authority stays null when it was not supplied.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BacklinkGap {

    /**
     * @param list<string>                                    $ourDomains
     * @param array<string, list<array{domain: string, authority: float|null}>> $competitors
     * @return list<array{domain: string, overlap: int, authority: float|null}>
     */
    public function missing( array $ourDomains, array $competitors ): array {
        $ours = [];
        foreach ( $ourDomains as $domain ) {
            $normalized = $this->normalize( $domain );
            if ( $normalized !== '' ) {
                $ours[ $normalized ] = true;
            }
        }
        $found = [];
        foreach ( $competitors as $links ) {
            $seen = [];
            foreach ( $links as $link ) {
                $domain = $this->normalize( $link['domain'] );
                if ( $domain === '' || isset( $ours[ $domain ] ) || isset( $seen[ $domain ] ) ) {
                    continue;
                }
                $seen[ $domain ] = true;
                if ( ! isset( $found[ $domain ] ) ) {
                    $found[ $domain ] = [
                        'domain'    => $domain,
                        'overlap'   => 0,
                        'authority' => $link['authority'],
                    ];
                }
                ++$found[ $domain ]['overlap'];
                if ( $found[ $domain ]['authority'] === null && $link['authority'] !== null ) {
                    $found[ $domain ]['authority'] = $link['authority'];
                }
            }
        }
        $rows = array_values( $found );
        usort(
            $rows,
            static function ( array $left, array $right ): int {
                if ( $left['overlap'] !== $right['overlap'] ) {
                    return $right['overlap'] <=> $left['overlap'];
                }
                if ( $left['authority'] === null && $right['authority'] === null ) {
                    return 0;
                }
                if ( $left['authority'] === null ) {
                    return 1;
                }
                if ( $right['authority'] === null ) {
                    return -1;
                }

                return $right['authority'] <=> $left['authority'];
            }
        );

        return $rows;
    }

    private function normalize( string $domain ): string {
        $domain = strtolower( trim( $domain ) );

        return str_starts_with( $domain, 'www.' ) ? substr( $domain, 4 ) : $domain;
    }
}
