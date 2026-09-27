<?php
/**
 * Keyword storage. Missing metrics are inserted as null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Keywords\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Keywords\Domain\KeywordRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class KeywordRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    public function save( KeywordRecord $record ): int {
        $now      = gmdate( 'Y-m-d H:i:s' );
        $data     = [
            'keyword_hash'       => $record->hash(),
            'keyword'            => $record->keyword,
            'country'            => $record->country,
            'language'           => $record->language,
            'device'             => 'desktop',
            'volume'             => $record->volume,
            'cpc'                => $record->cpc,
            'paid_competition'   => $record->paidCompetition,
            'organic_difficulty' => $record->organicDifficulty,
            'trend_json'         => $record->trend === null ? null : wp_json_encode( $record->trend ),
            'source'             => $record->source,
            'provider'           => $record->provider,
            'methodology'        => $record->methodology,
            'updated_at'         => $now,
        ];
        $existing = $this->database->select( $this->table(), [ 'keyword_hash' => $record->hash() ], 1 );
        if ( $existing === [] ) {
            $data['query_id']   = 0;
            $data['created_at'] = $now;

            return $this->database->insert( $this->table(), $data );
        }
        $this->database->update( $this->table(), $data, [ 'keyword_hash' => $record->hash() ] );

        return (int) ( $existing[0]['id'] ?? 0 );
    }

    /**
     * @return list<KeywordRecord>
     */
    public function all(): array {
        $rows = [];
        foreach ( $this->database->select( $this->table(), [], 200, 0, [ 'id' => 'ASC' ] ) as $row ) {
            $trend = null;
            if ( is_string( $row['trend_json'] ?? null ) && $row['trend_json'] !== '' ) {
                $decoded = json_decode( $row['trend_json'], true );
                if ( is_array( $decoded ) ) {
                    $trend = [];
                    foreach ( $decoded as $point ) {
                        if ( is_int( $point ) || is_float( $point ) ) {
                            $trend[] = (float) $point;
                        }
                    }
                }
            }
            $rows[] = new KeywordRecord(
                (int) ( $row['id'] ?? 0 ),
                (string) ( $row['keyword'] ?? '' ),
                (string) ( $row['country'] ?? '' ),
                (string) ( $row['language'] ?? '' ),
                isset( $row['volume'] ) && $row['volume'] !== '' ? (int) $row['volume'] : null,
                isset( $row['cpc'] ) && $row['cpc'] !== '' ? (string) $row['cpc'] : null,
                isset( $row['paid_competition'] ) && is_numeric( $row['paid_competition'] ) ? (float) $row['paid_competition'] : null,
                isset( $row['organic_difficulty'] ) && $row['organic_difficulty'] !== '' ? (int) $row['organic_difficulty'] : null,
                $trend,
                null,
                '',
                (string) ( $row['source'] ?? '' ),
                (string) ( $row['provider'] ?? '' ),
                isset( $row['methodology'] ) && is_string( $row['methodology'] ) && $row['methodology'] !== '' ? $row['methodology'] : null
            );
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent( int $limit = 20 ): array {
        $limit = max( 1, min( 20, $limit ) );

        return $this->database->select( $this->table(), [], $limit, 0, [ 'id' => 'DESC' ] );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_keywords';
    }
}
