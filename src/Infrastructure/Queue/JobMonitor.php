<?php
/**
 * Job list for the monitor. Retry puts a job back in the queue and does not run it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class JobMonitor {

    /**
     * @param list<array<string, mixed>> $jobs
     * @return list<array<string, mixed>>
     */
    public static function rows( array $jobs ): array {
        $rows = [];
        foreach ( $jobs as $job ) {
            if ( is_array( $job ) ) {
                $rows[] = self::row( $job );
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    public static function row( array $job ): array {
        $started   = self::stamp( $job['started_at'] ?? null );
        $completed = self::stamp( $job['completed_at'] ?? null );

        return [
            'id'             => isset( $job['id'] ) && is_numeric( $job['id'] ) ? (int) $job['id'] : null,
            'job'            => self::text( $job['job_type'] ?? null ),
            'module'         => self::module( self::text( $job['job_type'] ?? null ) ),
            'status'         => self::text( $job['status'] ?? null ),
            'created'        => self::text( $job['created_at'] ?? null ),
            'started'        => self::text( $job['started_at'] ?? null ),
            'duration'       => $started === null || $completed === null ? null : max( 0, $completed - $started ),
            'attempts'       => array_key_exists( 'attempt', $job ) && is_numeric( $job['attempt'] ) ? (int) $job['attempt'] : null,
            'correlation_id' => self::text( $job['correlation_id'] ?? null ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function retry( JobRepository $jobs, int $id, \DateTimeImmutable $now ): array {
        $job = $jobs->find( $id );
        if ( $job === null ) {
            return [
                'id'       => $id,
                'requeued' => false,
                'ran'      => false,
                'note'     => 'That job was not stored. The handler was not run.',
            ];
        }
        $requeued = $jobs->requeue( $id, $now );

        return [
            'id'       => $id,
            'requeued' => $requeued,
            'ran'      => false,
            'note'     => $requeued
                ? 'Retry put the job back in the queue. The handler was not run.'
                : 'This job cannot be retried. The handler was not run.',
        ];
    }

    private static function module( ?string $type ): ?string {
        if ( $type === null ) {
            return null;
        }
        $parts = explode( '.', $type );

        return $parts[1] ?? $parts[0];
    }

    private static function text( mixed $value ): ?string {
        if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
            return null;
        }
        $text = trim( (string) $value );

        return $text === '' ? null : $text;
    }

    private static function stamp( mixed $value ): ?int {
        if ( ! is_string( $value ) || trim( $value ) === '' ) {
            return null;
        }
        $stamp = strtotime( $value );

        return $stamp === false ? null : $stamp;
    }
}
