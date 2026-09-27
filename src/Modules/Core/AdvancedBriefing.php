<?php
/**
 * Advanced detail view. It is not the primary dashboard, and missing values stay empty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Backlinks\Infrastructure\BacklinkRepository;
use QueryNova\Modules\Keywords\Infrastructure\KeywordRepository;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;
use QueryNova\Modules\Serp\Infrastructure\SerpRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdvancedBriefing {

    private const LIMIT = 20;

    /**
     * @var list<string>
     */
    private const BANDS = [ 'HIGH', 'MEDIUM', 'LOW', 'UNKNOWN' ];

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function fromDatabase( DatabaseConnection $database ): array {
        $serp = new SerpRepository( $database );

        return $this->compose(
            ( new KeywordRepository( $database ) )->recent( self::LIMIT ),
            $serp->recentSnapshots( self::LIMIT ),
            ( new BacklinkRepository( $database ) )->recent( self::LIMIT ),
            $serp->recentRanks( self::LIMIT ),
            ( new RecommendationRepository( $database ) )->today()
        );
    }

    /**
     * Raw provider payloads are not reconstructed from normalized rows.
     *
     * @param list<array<string, mixed>> $keywords
     * @param list<array<string, mixed>> $snapshots
     * @param list<array<string, mixed>> $backlinks
     * @param list<array<string, mixed>> $ranks
     * @param list<array<string, mixed>> $recommendations
     * @param list<array<string, mixed>> $raw
     * @return array<string, list<array<string, mixed>>>
     */
    public function compose( array $keywords, array $snapshots, array $backlinks, array $ranks, array $recommendations, array $raw = [] ): array {
        $sections = self::emptySections();
        foreach ( $raw as $row ) {
            $this->push( $sections, 'raw', $this->titled( $row, 'raw' ) );
        }
        foreach ( $snapshots as $row ) {
            $this->push( $sections, 'serps', $this->snapshot( $row ) );
        }
        foreach ( $keywords as $row ) {
            $this->push( $sections, 'keywords', $this->keyword( $row ) );
            $this->push( $sections, 'methodologies', $this->methodology( $row ) );
        }
        foreach ( $backlinks as $row ) {
            $this->push( $sections, 'backlinks', $this->backlink( $row ) );
        }
        foreach ( $ranks as $row ) {
            $this->push( $sections, 'history', $this->rank( $row ) );
        }
        foreach ( $recommendations as $row ) {
            $this->push( $sections, 'confidence', $this->confidence( $row ) );
        }
        foreach ( array_merge( $keywords, $snapshots, $backlinks, $ranks ) as $row ) {
            $this->push( $sections, 'providers', $this->provider( $row ) );
        }

        return $sections;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function emptySections(): array {
        return [
            'raw'           => [],
            'serps'         => [],
            'keywords'      => [],
            'backlinks'     => [],
            'methodologies' => [],
            'providers'     => [],
            'confidence'    => [],
            'history'       => [],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function snapshot( array $row ): array {
        $provider = trim( (string) ( $row['provider'] ?? '' ) );
        $title    = $provider === '' ? 'SERP snapshot' : $provider;
        $status   = trim( (string) ( $row['status'] ?? '' ) );

        return $this->item( 'serp-' . $this->identity( $row, $title ), $title, $status, null );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function keyword( array $row ): ?array {
        $keyword = trim( (string) ( $row['keyword'] ?? '' ) );
        if ( $keyword === '' ) {
            return null;
        }
        $parts      = [];
        $place      = trim( trim( (string) ( $row['country'] ?? '' ) ) . ' ' . trim( (string) ( $row['language'] ?? '' ) ) );
        $difficulty = $this->nullableInt( $row['organic_difficulty'] ?? null );
        $paid       = $this->nullableFloat( $row['paid_competition'] ?? null );
        if ( $place !== '' ) {
            $parts[] = $place;
        }
        if ( $difficulty !== null ) {
            $parts[] = 'Organic difficulty ' . (string) $difficulty . ' · Estimated';
        }
        if ( $paid !== null ) {
            $parts[] = 'Paid competition ' . $this->plainNumber( $paid );
        }
        $volume = $this->nullableInt( $row['volume'] ?? null );
        $metric = $volume === null ? null : [
            'value' => $volume,
            'kind'  => 'MEASURED',
            'label' => 'Measured',
        ];

        return $this->item( 'keyword-' . $this->identity( $row, $keyword ), $keyword, implode( '. ', $parts ), $metric );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function backlink( array $row ): ?array {
        $title = trim( (string) ( $row['source_domain'] ?? '' ) );
        if ( $title === '' ) {
            $title = trim( (string) ( $row['source_url'] ?? '' ) );
        }
        if ( $title === '' ) {
            return null;
        }
        $authority = $row['authority'] ?? null;
        $metric    = is_int( $authority ) || is_float( $authority ) ? [
            'value' => (float) $authority,
            'kind'  => 'MEASURED',
            'label' => 'Measured',
        ] : null;

        return $this->item( 'backlink-' . $this->identity( $row, $title ), $title, trim( (string) ( $row['rel'] ?? '' ) ), $metric );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function methodology( array $row ): ?array {
        $name = trim( (string) ( $row['methodology'] ?? '' ) );
        if ( $name === '' ) {
            return null;
        }

        return $this->item( 'methodology-' . hash( 'sha256', $name ), $name, '', null );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function provider( array $row ): ?array {
        $name = trim( (string) ( $row['provider'] ?? '' ) );
        if ( $name === '' ) {
            return null;
        }

        return $this->item( 'provider-' . hash( 'sha256', $name ), $name, '', null );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function confidence( array $row ): ?array {
        $band  = strtoupper( trim( (string) ( $row['confidence'] ?? '' ) ) );
        $title = trim( (string) ( $row['title'] ?? '' ) );
        if ( $title === '' || ! in_array( $band, self::BANDS, true ) ) {
            return null;
        }

        return $this->item( 'confidence-' . $this->identity( $row, $title ), $title, $band, null );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function rank( array $row ): array {
        $url      = trim( (string) ( $row['url'] ?? '' ) );
        $title    = $url === '' ? 'Rank' : $url;
        $place    = trim( trim( (string) ( $row['country'] ?? '' ) ) . ' ' . trim( (string) ( $row['device'] ?? '' ) ) );
        $position = $row['position'] ?? null;
        if ( $position === null || $position === '' ) {
            $metric = [
                'value' => null,
                'kind'  => 'UNAVAILABLE',
                'label' => 'Unavailable',
            ];
        } elseif ( is_int( $position ) || is_float( $position ) || ( is_string( $position ) && is_numeric( $position ) ) ) {
            $metric = [
                'value' => (int) $position,
                'kind'  => 'MEASURED',
                'label' => 'Measured',
            ];
        } else {
            $metric = [
                'value' => null,
                'kind'  => 'UNAVAILABLE',
                'label' => 'Unavailable',
            ];
        }

        return $this->item( 'rank-' . $this->identity( $row, $title ), $title, $place, $metric );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function titled( array $row, string $prefix ): ?array {
        $title = trim( (string) ( $row['title'] ?? '' ) );
        if ( $title === '' ) {
            return null;
        }

        return $this->item( $prefix . '-' . $this->identity( $row, $title ), $title, trim( (string) ( $row['summary'] ?? '' ) ), null );
    }

    /**
     * @param array<string, list<array<string, mixed>>> $sections
     * @param array<string, mixed>|null                 $item
     */
    private function push( array &$sections, string $section, ?array $item ): void {
        if ( $item === null || ! isset( $sections[ $section ] ) || count( $sections[ $section ] ) >= self::LIMIT ) {
            return;
        }
        foreach ( $sections[ $section ] as $existing ) {
            if ( ( $existing['id'] ?? '' ) === $item['id'] ) {
                return;
            }
        }
        $sections[ $section ][] = $item;
    }

    /**
     * @param array<string, mixed>|null $metric
     * @return array<string, mixed>
     */
    private function item( string $id, string $title, string $summary, ?array $metric ): array {
        return [
            'id'      => $id,
            'title'   => $title,
            'summary' => $summary,
            'metric'  => $metric,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function identity( array $row, string $fallback ): string {
        if ( isset( $row['id'] ) && ( is_int( $row['id'] ) || ( is_string( $row['id'] ) && $row['id'] !== '' ) ) ) {
            return (string) $row['id'];
        }

        return hash( 'sha256', $fallback );
    }

    private function nullableInt( mixed $value ): ?int {
        if ( is_int( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) && is_numeric( $value ) ) {
            return (int) $value;
        }

        return null;
    }

    private function nullableFloat( mixed $value ): ?float {
        if ( is_int( $value ) || is_float( $value ) ) {
            return (float) $value;
        }
        if ( is_string( $value ) && is_numeric( $value ) ) {
            return (float) $value;
        }

        return null;
    }

    private function plainNumber( float $value ): string {
        $formatted = rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );

        return $formatted === '' ? '0' : $formatted;
    }
}
