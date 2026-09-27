<?php
/**
 * Log viewer storage.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

use QueryNova\Core\Logging\LogRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogRepository {

    public function __construct( private readonly DatabaseConnection $db ) {
    }

    public function insert( LogRecord $record ): int {
        $context = wp_json_encode( $record->context );

        return $this->db->insert(
            $this->table(),
            [
				'logged_at'       => $record->timestamp->format( 'Y-m-d H:i:s' ),
				'level'           => $record->level,
				'channel'         => $record->channel,
				'message'         => $record->message,
				'context_json'    => is_string( $context ) ? $context : '{}',
				'environment'     => $record->environment,
				'plugin_version'  => $record->pluginVersion,
				'request_id'      => $record->requestId,
				'correlation_id'  => $record->correlationId,
				'job_id'          => (string) $record->jobId,
				'module'          => $record->module,
				'provider'        => $record->provider,
				'exception_class' => $record->exceptionClass,
				'exception_code'  => $record->exceptionCode,
				'error_reference' => $record->errorReference,
			]
        );
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function search( array $filters, int $limit, int $offset ): array {
        $where = [];
        foreach ( [ 'level', 'channel', 'module', 'provider', 'request_id', 'correlation_id', 'error_reference' ] as $key ) {
            if ( ( $filters[ $key ] ?? '' ) !== '' ) {
                $where[ $key ] = $filters[ $key ];
            }
        }

        return $this->db->select( $this->table(), $where, $limit, $offset, [ 'id' => 'DESC' ] );
    }

    public function deleteOlderThan( \DateTimeImmutable $cutoff ): int {
        $rows    = $this->db->select( $this->table(), [], 5000, 0, [ 'id' => 'ASC' ] );
        $deleted = 0;
        foreach ( $rows as $row ) {
            $logged = isset( $row['logged_at'] ) ? strtotime( (string) $row['logged_at'] ) : false;
            if ( $logged !== false && $logged < $cutoff->getTimestamp() ) {
                $deleted += $this->db->delete( $this->table(), [ 'id' => (int) $row['id'] ] );
            }
        }

        return $deleted;
    }

    private function table(): string {
        return $this->db->prefix() . 'qn_logs';
    }
}
