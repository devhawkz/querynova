<?php
/**
 * Database port. Repositories depend on this, not on wpdb, so they can be tested.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface DatabaseConnection {

    public function prefix(): string;

    /**
     * @param array<string, mixed> $data
     */
    public function insert( string $table, array $data ): int;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update( string $table, array $data, array $where ): int;

    /**
     * @param array<string, mixed> $where
     */
    public function delete( string $table, array $where ): int;

    /**
     * @param array<string, mixed> $where
     * @param array<string, string> $orderBy
     * @return list<array<string, mixed>>
     */
    public function select( string $table, array $where = [], int $limit = 100, int $offset = 0, array $orderBy = [] ): array;

    /**
     * @param array<string, mixed> $where
     */
    public function count( string $table, array $where = [] ): int;

    public function query( string $sql ): bool;

    /**
     * @param list<mixed> $args
     * @return list<array<string, mixed>>
     */
    public function getResults( string $sql, array $args = [] ): array;

    /**
     * @param list<mixed> $args
     * @return array<string, mixed>|null
     */
    public function getRow( string $sql, array $args = [] ): ?array;

    /**
     * @param list<mixed> $args
     */
    public function getVar( string $sql, array $args = [] ): mixed;
}
