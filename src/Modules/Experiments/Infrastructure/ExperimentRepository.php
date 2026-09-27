<?php
/**
 * Stored experiments. The result describes metric movement, not a cause.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experiments\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperimentRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param array<string, mixed> $before
     */
    public function open( string $name, string $type, string $objectType, int $objectId, array $before, string $note ): int {
        $now = gmdate( 'Y-m-d H:i:s' );

        return $this->database->insert(
            $this->table(),
            [
                'name'            => $name,
                'experiment_type' => $type,
                'object_type'     => $objectType,
                'object_id'       => $objectId,
                'status'          => 'running',
                'started_at'      => $now,
                'ended_at'        => null,
                'before_json'     => wp_json_encode( $before ),
                'after_json'      => null,
                'result'          => 'inconclusive',
                'causation_note'  => $note,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );
    }

    /**
     * @param array<string, mixed> $after
     */
    public function close( int $id, array $after, string $result, string $note ): bool {
        $existing = $this->find( $id );
        if ( $existing === null || (string) $existing['status'] !== 'running' ) {
            return false;
        }
        $now = gmdate( 'Y-m-d H:i:s' );

        return $this->database->update(
            $this->table(),
            [
                'status'         => 'complete',
                'ended_at'       => $now,
                'after_json'     => wp_json_encode( $after ),
                'result'         => $result,
                'causation_note' => $note,
                'updated_at'     => $now,
            ],
            [ 'id' => $id ]
        ) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find( int $id ): ?array {
        $rows = $this->database->select( $this->table(), [ 'id' => $id ], 1 );

        return $rows[0] ?? null;
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_experiments';
    }
}
