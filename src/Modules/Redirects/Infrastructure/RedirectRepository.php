<?php
/**
 * Redirect storage. Lookup is by source hash, not a scan of every URL.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Redirects\Domain\RedirectRule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @return list<RedirectRule>
     */
    public function enabled(): array {
        return $this->map( $this->database->select( $this->table(), [ 'enabled' => 1 ], 5000 ) );
    }

    /**
     * @return list<RedirectRule>
     */
    public function all(): array {
        return $this->map( $this->database->select( $this->table(), [], 5000, 0, [ 'id' => 'ASC' ] ) );
    }

    public function save( RedirectRule $rule ): int {
        $now  = gmdate( 'Y-m-d H:i:s' );
        $data = [
            'source'      => $rule->source(),
            'source_hash' => hash( 'sha256', $rule->source() ),
            'target'      => $rule->target(),
            'status_code' => $rule->status(),
            'is_regex'    => $rule->regex() ? 1 : 0,
            'enabled'     => $rule->enabled() ? 1 : 0,
            'updated_at'  => $now,
        ];
        if ( $rule->id() > 0 ) {
            $this->database->update( $this->table(), $data, [ 'id' => $rule->id() ] );

            return $rule->id();
        }
        $data['hits']       = 0;
        $data['created_at'] = $now;

        return $this->database->insert( $this->table(), $data );
    }

    public function delete( int $id ): void {
        $this->database->delete( $this->table(), [ 'id' => $id ] );
    }

    public function increment( int $id ): void {
        $rows = $this->database->select( $this->table(), [ 'id' => $id ], 1 );
        $hits = isset( $rows[0]['hits'] ) ? (int) $rows[0]['hits'] : 0;
        $this->database->update( $this->table(), [ 'hits' => $hits + 1 ], [ 'id' => $id ] );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<RedirectRule>
     */
    private function map( array $rows ): array {
        $rules = [];
        foreach ( $rows as $row ) {
            $rules[] = new RedirectRule(
                (int) ( $row['id'] ?? 0 ),
                (string) ( $row['source'] ?? '' ),
                (string) ( $row['target'] ?? '' ),
                (int) ( $row['status_code'] ?? 0 ),
                (int) ( $row['is_regex'] ?? 0 ) === 1,
                (int) ( $row['hits'] ?? 0 ),
                (int) ( $row['enabled'] ?? 0 ) === 1
            );
        }

        return $rules;
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_redirects';
    }
}
