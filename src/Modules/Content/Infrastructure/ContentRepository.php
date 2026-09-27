<?php
/**
 * Stored content observations. Completeness and information-gain scores stay null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentRepository {

    public const METHODOLOGY         = 'querynova.content_observations';
    public const METHODOLOGY_VERSION = 'v1';

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param array<string, mixed> $report
     */
    public function save( int $pageId, array $report ): int {
        $now = gmdate( 'Y-m-d H:i:s' );

        return $this->database->insert(
            $this->table( 'content_analysis' ),
            [
                'page_id'             => $pageId,
                'analyzed_at'         => $now,
                'completeness'        => null,
                'topics_json'         => wp_json_encode( $report['coverage'] ?? null ),
                'entities_json'       => wp_json_encode( $report['entities'] ?? null ),
                'information_gain'    => null,
                'eeat_json'           => wp_json_encode( $report['evidence'] ?? null ),
                'methodology'         => self::METHODOLOGY,
                'methodology_version' => self::METHODOLOGY_VERSION,
                'confidence'          => null,
                'source'              => 'supplied_document',
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest( int $pageId ): ?array {
        $rows = $this->database->select(
            $this->table( 'content_analysis' ),
            [ 'page_id' => $pageId ],
            1,
            0,
            [ 'id' => 'DESC' ]
        );
        if ( $rows === [] ) {
            return null;
        }

        return $rows[0];
    }

    /**
     * @param list<array{name: string, type: string, relation: string}> $mentions
     */
    public function saveEntities( array $mentions ): void {
        $now = gmdate( 'Y-m-d H:i:s' );
        $ids = [];
        foreach ( $mentions as $mention ) {
            $hash     = hash( 'sha256', strtolower( $mention['type'] . '|' . $mention['name'] ) );
            $existing = $this->database->select(
                $this->table( 'entities' ),
                [
                    'entity_type' => $mention['type'],
                    'name_hash'   => $hash,
                ],
                1
            );
            if ( $existing === [] ) {
                $ids[] = $this->database->insert(
                    $this->table( 'entities' ),
                    [
                        'entity_type'     => $mention['type'],
                        'name'            => $mention['name'],
                        'name_hash'       => $hash,
                        'external_id'     => '',
                        'attributes_json' => null,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]
                );
                continue;
            }
            $ids[] = (int) $existing[0]['id'];
        }
        $count = count( $ids );
        for ( $left = 0; $left < $count; $left++ ) {
            for ( $right = $left + 1; $right < $count; $right++ ) {
                $this->relate( $ids[ $left ], $ids[ $right ], 'co_mentioned', $now );
            }
        }
    }

    private function relate( int $from, int $to, string $relation, string $now ): void {
        $existing = $this->database->select(
            $this->table( 'entity_relations' ),
            [
                'from_entity_id' => $from,
                'to_entity_id'   => $to,
                'relation_type'  => $relation,
            ],
            1
        );
        if ( $existing !== [] ) {
            return;
        }
        $this->database->insert(
            $this->table( 'entity_relations' ),
            [
                'from_entity_id' => $from,
                'to_entity_id'   => $to,
                'relation_type'  => $relation,
                'created_at'     => $now,
            ]
        );
    }

    private function table( string $name ): string {
        return $this->database->prefix() . 'qn_' . $name;
    }
}
