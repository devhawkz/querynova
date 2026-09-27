<?php
/**
 * Stored recommendations. Suggested rows are not applied.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Opportunities\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RecommendationRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param list<array<string, mixed>> $recommendations
     */
    public function save( array $recommendations ): int {
        $stored = 0;
        $now    = gmdate( 'Y-m-d H:i:s' );
        foreach ( $recommendations as $row ) {
            $title = trim( (string) ( $row['title'] ?? '' ) );
            $query = trim( (string) ( $row['target_query'] ?? '' ) );
            $url   = trim( (string) ( $row['url'] ?? '' ) );
            if ( $title === '' ) {
                continue;
            }
            $key      = hash( 'sha256', $title . '|' . $url . '|' . $query );
            $existing = $this->database->select( $this->table(), [ 'idempotency_key' => $key ], 1 );
            $data     = [
                'title'             => $title,
                'description'       => (string) ( $row['description'] ?? '' ),
                'url'               => $url === '' ? null : $url,
                'target_query'      => $query,
                'impact'            => (string) ( $row['impact'] ?? 'unavailable' ),
                'confidence'        => (string) ( $row['confidence'] ?? 'UNKNOWN' ),
                'effort'            => (string) ( $row['effort'] ?? 'unknown' ),
                'evidence_json'     => wp_json_encode( $row['evidence'] ?? [] ),
                'data_sources_json' => wp_json_encode( $row['data_sources'] ?? [] ),
                'expected_kpi'      => (string) ( $row['expected_kpi'] ?? '' ),
                'rationale'         => (string) ( $row['rationale'] ?? '' ),
                'priority'          => (string) ( $row['priority'] ?? '' ),
                'status'            => 'suggested',
                'outcome'           => '',
                'idempotency_key'   => $key,
                'updated_at'        => $now,
            ];
            if ( $existing === [] ) {
                $data['created_at'] = $now;
                $this->database->insert( $this->table(), $data );
            } else {
                $this->database->update( $this->table(), $data, [ 'idempotency_key' => $key ] );
            }
            ++$stored;
        }

        return $stored;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function today(): array {
        return $this->database->select( $this->table(), [ 'status' => 'suggested' ], 50, 0, [ 'id' => 'ASC' ] );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_recommendations';
    }
}
