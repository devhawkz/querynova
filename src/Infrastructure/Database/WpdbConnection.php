<?php
/**
 * wpdb adapter. SQL is prepared here, not in controllers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

// Identifiers are validated before interpolation. Values are passed through $wpdb->prepare().
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared

use QueryNova\Core\Exceptions\DatabaseException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WpdbConnection implements DatabaseConnection {

    public function prefix(): string {
        global $wpdb;

        return (string) $wpdb->prefix;
    }

    public function insert( string $table, array $data ): int {
        global $wpdb;
        $ok = $wpdb->insert( $table, $data );
        if ( $ok === false ) {
            throw new DatabaseException( 'Insert failed.' );
        }

        return (int) $wpdb->insert_id;
    }

    public function update( string $table, array $data, array $where ): int {
        global $wpdb;
        $ok = $wpdb->update( $table, $data, $where );
        if ( $ok === false ) {
            throw new DatabaseException( 'Update failed.' );
        }

        return (int) $ok;
    }

    public function delete( string $table, array $where ): int {
        global $wpdb;
        $ok = $wpdb->delete( $table, $where );
        if ( $ok === false ) {
            throw new DatabaseException( 'Delete failed.' );
        }

        return (int) $ok;
    }

    public function select( string $table, array $where = [], int $limit = 100, int $offset = 0, array $orderBy = [] ): array {
        global $wpdb;
        [$sql, $args] = $this->selectSql( $table, $where, $limit, $offset, $orderBy );
        $prepared     = $args === [] ? $sql : $wpdb->prepare( $sql, ...$args );
        $rows         = $wpdb->get_results( $prepared, 'ARRAY_A' );

        return is_array( $rows ) ? $rows : [];
    }

    public function count( string $table, array $where = [] ): int {
        global $wpdb;
        $table = $this->assertIdentifier( $table );
        $args  = [];
        $sql   = "SELECT COUNT(*) FROM {$table}";
        if ( $where !== [] ) {
            [$clause, $args] = $this->where( $where );
            $sql            .= ' WHERE ' . $clause;
        }
        $prepared = $args === [] ? $sql : $wpdb->prepare( $sql, ...$args );

        return (int) $wpdb->get_var( $prepared );
    }

    public function query( string $sql ): bool {
        global $wpdb;
        $result = $wpdb->query( $sql );

        return $result !== false;
    }

    public function getResults( string $sql, array $args = [] ): array {
        global $wpdb;
        $prepared = $args === [] ? $sql : $wpdb->prepare( $sql, ...$args );
        $rows     = $wpdb->get_results( $prepared, 'ARRAY_A' );

        return is_array( $rows ) ? $rows : [];
    }

    public function getRow( string $sql, array $args = [] ): ?array {
        global $wpdb;
        $prepared = $args === [] ? $sql : $wpdb->prepare( $sql, ...$args );
        $row      = $wpdb->get_row( $prepared, 'ARRAY_A' );

        return is_array( $row ) ? $row : null;
    }

    public function getVar( string $sql, array $args = [] ): mixed {
        global $wpdb;
        $prepared = $args === [] ? $sql : $wpdb->prepare( $sql, ...$args );

        return $wpdb->get_var( $prepared );
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, string> $orderBy
     * @return array{0: string, 1: list<mixed>}
     */
    private function selectSql( string $table, array $where, int $limit, int $offset, array $orderBy ): array {
        $table = $this->assertIdentifier( $table );
        $args  = [];
        $sql   = "SELECT * FROM {$table}";
        if ( $where !== [] ) {
            [$clause, $args] = $this->where( $where );
            $sql            .= ' WHERE ' . $clause;
        }
        if ( $orderBy !== [] ) {
            $parts = [];
            foreach ( $orderBy as $column => $direction ) {
                $column    = $this->assertIdentifier( (string) $column );
                $direction = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
                $parts[]   = "{$column} {$direction}";
            }
            $sql .= ' ORDER BY ' . implode( ', ', $parts );
        }
        $sql   .= ' LIMIT %d OFFSET %d';
        $args[] = max( 0, $limit );
        $args[] = max( 0, $offset );

        return [ $sql, $args ];
    }

    /**
     * @param array<string, mixed> $where
     * @return array{0: string, 1: list<mixed>}
     */
    private function where( array $where ): array {
        $parts = [];
        $args  = [];
        foreach ( $where as $column => $value ) {
            $column = $this->assertIdentifier( (string) $column );
            if ( $value === null ) {
                $parts[] = "{$column} IS NULL";
                continue;
            }
            $parts[] = "{$column} = " . ( is_int( $value ) ? '%d' : '%s' );
            $args[]  = $value;
        }

        return [ implode( ' AND ', $parts ), $args ];
    }

    private function assertIdentifier( string $identifier ): string {
        if ( preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) !== 1 ) {
            throw new DatabaseException( 'Invalid SQL identifier.' );
        }

        return $identifier;
    }
}
