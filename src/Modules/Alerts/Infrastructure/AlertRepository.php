<?php
/**
 * Stored alerts. The same evidence does not create a second open row.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Alerts\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AlertRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param list<array{type: string, severity: string, title: string, message: string}> $alerts
     */
    public function store( string $objectKey, array $alerts ): int {
        $stored = 0;
        $now    = gmdate( 'Y-m-d H:i:s' );
        foreach ( $alerts as $alert ) {
            $key      = hash( 'sha256', $objectKey . '|' . $alert['type'] );
            $existing = $this->database->select(
                $this->table(),
                [
					'idempotency_key' => $key,
					'status'          => 'open',
				],
				1
            );
            if ( $existing !== [] ) {
                continue;
            }
            $this->database->insert(
                $this->table(),
                [
                    'alert_type'      => $alert['type'],
                    'severity'        => $alert['severity'],
                    'title'           => $alert['title'],
                    'message'         => $alert['message'],
                    'status'          => 'open',
                    'idempotency_key' => $key,
                    'created_at'      => $now,
                    'acknowledged_at' => null,
                ]
            );
            ++$stored;
        }

        return $stored;
    }

    public function openCount(): int {
        return count( $this->database->select( $this->table(), [ 'status' => 'open' ], 100 ) );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_alerts';
    }
}
