<?php
/**
 * Local rank-tracker keywords. Adding a keyword does not call a SERP vendor.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RankTracker {

    public const OPTION = 'querynova_rank_keywords';

    private const LIMIT = 200;

    /**
     * @return list<array{id: int, keyword: string, group: string, location: string, language: string, device: string, country: string}>
     */
    public static function keywords(): array {
        $stored = get_option( self::OPTION, null );
        $rows   = is_array( $stored ) ? $stored : [];
        $clean  = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $keyword = self::text( $row['keyword'] ?? '' );
            if ( $keyword === '' ) {
                continue;
            }
            $clean[] = [
                'id'       => max( 0, (int) ( $row['id'] ?? 0 ) ),
                'keyword'  => $keyword,
                'group'    => self::text( $row['group'] ?? '' ),
                'location' => self::text( $row['location'] ?? '' ),
                'language' => strtolower( self::text( $row['language'] ?? '' ) ),
                'device'   => self::device( $row['device'] ?? '' ),
                'country'  => strtolower( self::text( $row['country'] ?? '' ) ),
            ];
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function add( array $input ): array {
        $keyword = self::text( $input['keyword'] ?? '' );
        if ( $keyword === '' ) {
            return self::result( 'invalid', 'A keyword is required.', 0, 0 );
        }
        $device = strtolower( self::text( $input['device'] ?? 'desktop' ) );
        if ( $device === '' ) {
            $device = 'desktop';
        }
        if ( ! in_array( $device, [ 'desktop', 'mobile', 'tablet' ], true ) ) {
            return self::result( 'invalid', 'Device must be desktop, mobile, or tablet.', 0, 0 );
        }
        $keywords = self::keywords();
        if ( count( $keywords ) >= self::LIMIT ) {
            return self::result( 'invalid', 'The tracker already has 200 keywords.', 0, 1 );
        }
        $next = 1;
        foreach ( $keywords as $row ) {
            $next = max( $next, $row['id'] + 1 );
        }
        $keywords[] = [
            'id'       => $next,
            'keyword'  => $keyword,
            'group'    => self::text( $input['group'] ?? '' ),
            'location' => self::text( $input['location'] ?? '' ),
            'language' => strtolower( self::text( $input['language'] ?? '' ) ),
            'device'   => $device,
            'country'  => strtolower( self::text( $input['country'] ?? '' ) ),
        ];
        update_option( self::OPTION, $keywords, false );

        return self::result( 'stored', 'The keyword is stored locally. No SERP vendor was called. A missing rank is not zero.', 1, 0 );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function addMany( array $rows ): array {
        $added   = 0;
        $skipped = 0;
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                ++$skipped;
                continue;
            }
            $result = self::add( $row );
            if ( ( $result['added'] ?? 0 ) === 1 ) {
                ++$added;
                continue;
            }
            ++$skipped;
        }

        return self::result( 'stored', 'Keywords are stored locally. No SERP vendor was called.', $added, $skipped );
    }

    /**
     * @return array<string, mixed>
     */
    public static function importCsv( string $csv ): array {
        $lines = preg_split( '/\r\n|\n|\r/', $csv );
        $rows  = [];
        if ( ! is_array( $lines ) ) {
            return self::addMany( [] );
        }
        foreach ( $lines as $line ) {
            if ( trim( $line ) === '' ) {
                continue;
            }
            $cells = str_getcsv( $line, ',', '"', '\\' );
            if ( isset( $cells[0] ) && strtolower( trim( (string) $cells[0] ) ) === 'keyword' && $rows === [] ) {
                continue;
            }
            $rows[] = [
                'keyword'  => (string) ( $cells[0] ?? '' ),
                'group'    => (string) ( $cells[1] ?? '' ),
                'location' => (string) ( $cells[2] ?? '' ),
                'language' => (string) ( $cells[3] ?? '' ),
                'device'   => (string) ( $cells[4] ?? 'desktop' ),
                'country'  => (string) ( $cells[5] ?? '' ),
            ];
        }

        return self::addMany( $rows );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function history( array $rows ): array {
        if ( $rows === [] ) {
            return [
                'status' => 'unavailable',
                'rows'   => [],
                'note'   => 'No stored rank history. A missing rank is not zero. No SERP vendor is selected.',
            ];
        }
        $clean = [];
        foreach ( $rows as $row ) {
            $position = $row['position'] ?? null;
            $clean[]  = [
                'keyword_id' => (int) ( $row['keyword_id'] ?? 0 ),
                'position'   => is_numeric( $position ) ? (int) $position : null,
                'url'        => is_string( $row['url'] ?? null ) ? $row['url'] : '',
                'device'     => is_string( $row['device'] ?? null ) ? $row['device'] : '',
                'language'   => is_string( $row['language'] ?? null ) ? $row['language'] : '',
                'country'    => is_string( $row['country'] ?? null ) ? $row['country'] : '',
            ];
        }

        return [
            'status' => 'stored',
            'rows'   => $clean,
            'note'   => 'Stored rank history. A missing position stays empty. No SERP vendor was called for this response.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array {
        return [
            'keywords'     => self::keywords(),
            'history'      => self::history( [] ),
            'index_status' => IndexAvailability::report( '', null, 'Index status' ),
            'trends'       => IndexAvailability::report( '', null, 'Trends' ),
            'note'         => 'No SERP vendor is selected.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function result( string $status, string $note, int $added, int $skipped ): array {
        return [
            'status'   => $status,
            'added'    => $added,
            'skipped'  => $skipped,
            'keywords' => self::keywords(),
            'note'     => $note,
        ];
    }

    private static function device( mixed $value ): string {
        $device = strtolower( self::text( $value ) );

        return in_array( $device, [ 'desktop', 'mobile', 'tablet' ], true ) ? $device : 'desktop';
    }

    private static function text( mixed $value ): string {
        return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
    }
}
