<?php
/**
 * Job persistence and atomic claim.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class JobRepository {

    public function __construct( private readonly DatabaseConnection $db ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(
        string $type,
        array $payload,
        string $idempotencyKey,
        string $correlationId,
        \DateTimeImmutable $scheduledAt,
        int $priority = 10,
        int $maxAttempts = 5,
    ): int {
        $existing = $this->findByIdempotency( $idempotencyKey );
        if ( $existing !== null ) {
            return (int) $existing['id'];
        }

        $encoded = wp_json_encode( $payload );

        return $this->db->insert(
            $this->table(),
            [
				'job_type'        => $type,
				'payload'         => is_string( $encoded ) ? $encoded : '{}',
				'priority'        => $priority,
				'attempt'         => 0,
				'max_attempts'    => $maxAttempts,
				'status'          => JobStatus::Pending->value,
				'correlation_id'  => $correlationId,
				'idempotency_key' => $idempotencyKey,
				'scheduled_at'    => $scheduledAt->format( 'Y-m-d H:i:s' ),
				'started_at'      => null,
				'completed_at'    => null,
				'last_error'      => null,
				'error_reference' => '',
				'created_at'      => $scheduledAt->format( 'Y-m-d H:i:s' ),
			]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find( int $id ): ?array {
        $rows = $this->db->select( $this->table(), [ 'id' => $id ], 1, 0 );

        return $rows[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotency( string $key ): ?array {
        $rows = $this->db->select( $this->table(), [ 'idempotency_key' => $key ], 1, 0 );

        return $rows[0] ?? null;
    }

    public function claim( int $id, \DateTimeImmutable $now ): bool {
        $job = $this->find( $id );
        if ( $job === null ) {
            return false;
        }
        $status = (string) ( $job['status'] ?? '' );
        if ( ! in_array( $status, [ JobStatus::Pending->value, JobStatus::Retrying->value ], true ) ) {
            return false;
        }
        $scheduled = strtotime( (string) ( $job['scheduled_at'] ?? '' ) );
        if ( $scheduled !== false && $scheduled > $now->getTimestamp() ) {
            return false;
        }

        $updated = $this->db->update(
            $this->table(),
            [
				'status'     => JobStatus::Running->value,
				'started_at' => $now->format( 'Y-m-d H:i:s' ),
				'attempt'    => ( (int) $job['attempt'] ) + 1,
            ],
            [
				'id'     => $id,
				'status' => $status,
			]
        );

        return $updated === 1;
    }

    public function complete( int $id, \DateTimeImmutable $now ): void {
        $this->db->update(
            $this->table(),
            [
				'status'       => JobStatus::Completed->value,
				'completed_at' => $now->format( 'Y-m-d H:i:s' ),
				'last_error'   => null,
			],
            [ 'id' => $id ]
        );
    }

    public function markRetry( int $id, \DateTimeImmutable $scheduledAt, string $error, string $reference ): void {
        $this->db->update(
            $this->table(),
            [
				'status'          => JobStatus::Retrying->value,
				'scheduled_at'    => $scheduledAt->format( 'Y-m-d H:i:s' ),
				'last_error'      => $error,
				'error_reference' => $reference,
			],
            [ 'id' => $id ]
        );
    }

    public function markDead( int $id, string $error, string $reference, \DateTimeImmutable $now ): void {
        $this->db->update(
            $this->table(),
            [
				'status'          => JobStatus::Dead->value,
				'completed_at'    => $now->format( 'Y-m-d H:i:s' ),
				'last_error'      => $error,
				'error_reference' => $reference,
			],
            [ 'id' => $id ]
        );
    }

    public function cancel( int $id ): bool {
        $job = $this->find( $id );
        if ( $job === null ) {
            return false;
        }
        if ( in_array( (string) $job['status'], [ JobStatus::Completed->value, JobStatus::Cancelled->value ], true ) ) {
            return false;
        }
        $this->db->update( $this->table(), [ 'status' => JobStatus::Cancelled->value ], [ 'id' => $id ] );

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function due( int $limit, \DateTimeImmutable $now ): array {
        $rows = $this->db->select(
            $this->table(),
            [],
            500,
            0,
            [
				'priority' => 'ASC',
				'id'       => 'ASC',
			]
        );
        $due  = [];
        foreach ( $rows as $row ) {
            if ( ! in_array( (string) $row['status'], [ JobStatus::Pending->value, JobStatus::Retrying->value ], true ) ) {
                continue;
            }
            $scheduled = strtotime( (string) $row['scheduled_at'] );
            if ( $scheduled !== false && $scheduled <= $now->getTimestamp() ) {
                $due[] = $row;
            }
            if ( count( $due ) >= $limit ) {
                break;
            }
        }

        return $due;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list( string $status, int $limit, int $offset ): array {
        $where = $status === '' ? [] : [ 'status' => $status ];

        return $this->db->select( $this->table(), $where, $limit, $offset, [ 'id' => 'DESC' ] );
    }

    private function table(): string {
        return $this->db->prefix() . 'qn_jobs';
    }
}
