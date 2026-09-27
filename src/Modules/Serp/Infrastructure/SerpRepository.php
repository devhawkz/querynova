<?php
/**
 * Stored SERP snapshots, ranks, and competitor rows.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Serp\Domain\SerpHit;
use QueryNova\Modules\Serp\Domain\SerpQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param list<string>|null $features
     * @param list<SerpHit>     $hits
     */
    public function saveSnapshot( SerpQuery $query, string $provider, string $status, ?array $features, ?string $stability, array $hits ): int {
        $now = gmdate( 'Y-m-d H:i:s' );
        $id  = $this->database->insert(
            $this->table( 'serp_snapshots' ),
            [
                'keyword_id'     => $query->keywordId,
                'location'       => $query->location,
                'country'        => $query->country,
                'language'       => $query->language,
                'device'         => $query->device,
                'captured_at'    => $now,
                'provider'       => $provider,
                'status'         => $status,
                'features_json'  => $features === null ? null : wp_json_encode( $features ),
                'stability'      => $stability,
                'correlation_id' => '',
                'source'         => $status === 'ok' ? 'provider' : 'unavailable',
            ]
        );
        foreach ( $hits as $hit ) {
            $this->database->insert(
                $this->table( 'serp_results' ),
                [
                    'snapshot_id'   => $id,
                    'position'      => $hit->position,
                    'url'           => $hit->url,
                    'url_hash'      => hash( 'sha256', $hit->url ),
                    'domain'        => $hit->domain,
                    'title'         => $hit->title,
                    'snippet'       => $hit->snippet,
                    'page_type'     => $hit->pageType,
                    'features_json' => $hit->features === null ? null : wp_json_encode( $hit->features ),
                ]
            );
        }

        return $id;
    }

    /**
     * @return list<string>
     */
    public function previousUrls( SerpQuery $query ): array {
        $rows = $this->database->select(
            $this->table( 'serp_snapshots' ),
            [
                'keyword_id' => $query->keywordId,
                'country'    => $query->country,
                'language'   => $query->language,
                'device'     => $query->device,
                'status'     => 'ok',
            ],
            1,
            0,
            [ 'id' => 'DESC' ]
        );
        if ( $rows === [] ) {
            return [];
        }
        $urls = [];
        foreach ( $this->database->select( $this->table( 'serp_results' ), [ 'snapshot_id' => (int) $rows[0]['id'] ], 10, 0, [ 'position' => 'ASC' ] ) as $row ) {
            $urls[] = (string) ( $row['url'] ?? '' );
        }

        return $urls;
    }

    public function saveRank( SerpQuery $query, string $provider, ?int $position, string $url ): void {
        $this->database->insert(
            $this->table( 'rank_history' ),
            [
                'keyword_id'  => $query->keywordId,
                'page_id'     => 0,
                'position'    => $position,
                'url'         => $url === '' ? null : $url,
                'country'     => $query->country,
                'device'      => $query->device,
                'language'    => $query->language,
                'captured_at' => gmdate( 'Y-m-d H:i:s' ),
                'provider'    => $provider,
                'source'      => 'provider',
            ]
        );
    }

    /**
     * @param list<array<string, mixed>> $competitors
     */
    public function saveCompetitors( SerpQuery $query, string $provider, array $competitors ): void {
        $now = gmdate( 'Y-m-d H:i:s' );
        foreach ( $competitors as $row ) {
            $metrics = wp_json_encode(
                [
                    'page_type'         => $row['page_type'] ?? '',
                    'authority'         => null,
                    'referring_domains' => null,
                    'backlinks'         => null,
                    'traffic'           => null,
                ]
            );
            $this->database->insert(
                $this->table( 'competitors' ),
                [
                    'domain'            => (string) ( $row['domain'] ?? '' ),
                    'url'               => (string) ( $row['url'] ?? '' ),
                    'keyword_id'        => $query->keywordId,
                    'page_id'           => 0,
                    'authority'         => null,
                    'referring_domains' => null,
                    'metrics_json'      => is_string( $metrics ) ? $metrics : '{}',
                    'source'            => 'serp',
                    'provider'          => $provider,
                    'observed_at'       => $now,
                ]
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history( int $keywordId ): array {
        return $this->database->select( $this->table( 'serp_snapshots' ), [ 'keyword_id' => $keywordId ], 20, 0, [ 'id' => 'DESC' ] );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentSnapshots( int $limit = 20 ): array {
        $limit = max( 1, min( 20, $limit ) );

        return $this->database->select( $this->table( 'serp_snapshots' ), [], $limit, 0, [ 'id' => 'DESC' ] );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentRanks( int $limit = 20 ): array {
        $limit = max( 1, min( 20, $limit ) );

        return $this->database->select( $this->table( 'rank_history' ), [], $limit, 0, [ 'id' => 'DESC' ] );
    }

    private function table( string $name ): string {
        return $this->database->prefix() . 'qn_' . $name;
    }
}
