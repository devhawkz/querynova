<?php
/**
 * Audit rows are separate from operational logs. Raw IP addresses are not stored.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Audit\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AuditRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record( int $userId, string $action, string $objectType, int $objectId, ?array $before, ?array $after, string $ip, string $environment ): int {
        return $this->database->insert(
            $this->table(),
            [
                'user_id'     => $userId,
                'action'      => $action,
                'object_type' => $objectType,
                'object_id'   => $objectId,
                'before_json' => $before === null ? null : wp_json_encode( $before ),
                'after_json'  => $after === null ? null : wp_json_encode( $after ),
                'ip_hash'     => $ip === '' ? '' : hash( 'sha256', $ip ),
                'environment' => $environment,
                'created_at'  => gmdate( 'Y-m-d H:i:s' ),
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find( int $id ): ?array {
        $rows = $this->database->select( $this->table(), [ 'id' => $id ], 1 );

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history( string $objectType, int $objectId ): array {
        return $this->database->select(
            $this->table(),
            [
                'object_type' => $objectType,
                'object_id'   => $objectId,
            ],
            50,
            0,
            [ 'id' => 'ASC' ]
        );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_audit_log';
    }
}
