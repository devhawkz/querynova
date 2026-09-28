<?php
/**
 * Decay and cannibalization from stored rows. Nothing is crawled.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentHealth {

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function decay( array $rows ): array {
        $report = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $current  = self::side( $row['current'] ?? null );
            $previous = self::side( $row['previous'] ?? null );
            $report[] = [
                'url'       => self::text( $row['url'] ?? '' ),
                'current'   => $current,
                'previous'  => $previous,
                'change'    => self::change( $current, $previous ),
                'causation' => false,
            ];
        }

        return [
            'kind'      => 'decay',
            'rows'      => $report,
            'crawled'   => false,
            'causation' => false,
            'note'      => 'Decay reads stored metrics. A missing side stays empty. This does not claim a cause.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function cannibalization( array $rows ): array {
        $groups = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $keyword = strtolower( self::text( $row['keyword'] ?? '' ) );
            $url     = self::text( $row['url'] ?? '' );
            if ( $keyword === '' || $url === '' ) {
                continue;
            }
            $groups[ $keyword ][ $url ] = $url;
        }
        $conflicts = [];
        foreach ( $groups as $keyword => $urls ) {
            if ( count( $urls ) < 2 ) {
                continue;
            }
            $conflicts[] = [
                'keyword'   => $keyword,
                'urls'      => array_values( $urls ),
                'causation' => false,
            ];
        }

        return [
            'kind'      => 'cannibalization',
            'rows'      => $conflicts,
            'crawled'   => false,
            'causation' => false,
            'note'      => 'Cannibalization reads stored keyword rows. A missing keyword or URL is left out. This does not claim a cause.',
        ];
    }

    /**
     * @return array{clicks: int|float|null, impressions: int|float|null, position: int|float|null}
     */
    private static function side( mixed $side ): array {
        $side = is_array( $side ) ? $side : [];

        return [
            'clicks'      => self::metric( $side['clicks'] ?? null ),
            'impressions' => self::metric( $side['impressions'] ?? null ),
            'position'    => self::metric( $side['position'] ?? null ),
        ];
    }

    /**
     * @param array{clicks: int|float|null, impressions: int|float|null, position: int|float|null} $current
     * @param array{clicks: int|float|null, impressions: int|float|null, position: int|float|null} $previous
     * @return array<string, string|null>
     */
    private static function change( array $current, array $previous ): array {
        $change = [];
        foreach ( [ 'clicks', 'impressions', 'position' ] as $key ) {
            if ( $current[ $key ] === null || $previous[ $key ] === null ) {
                $change[ $key ] = null;
                continue;
            }
            $change[ $key ] = 'recorded';
        }

        return $change;
    }

    private static function metric( mixed $value ): int|float|null {
        if ( is_int( $value ) || is_float( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) && is_numeric( $value ) ) {
            return $value + 0;
        }

        return null;
    }

    private static function text( mixed $value ): string {
        return trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );
    }
}
