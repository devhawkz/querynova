<?php
/**
 * In-memory database for unit tests and local fakes. Not used in production.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ArrayDatabase implements DatabaseConnection {

    /** @var array<string, list<array<string, mixed>>> */
    private array $tables = [];

    private int $nextId = 1;

    /** @var list<string> */
    private array $statements = [];

    public function __construct( private readonly string $tablePrefix = 'wp_' ) {
    }

    public function prefix(): string {
        return $this->tablePrefix;
    }

    public function insert( string $table, array $data ): int {
        $id = $this->nextId++;
        if ( ! isset( $data['id'] ) ) {
            $data['id'] = $id;
        } else {
            $id = (int) $data['id'];
        }
        $this->tables[ $table ][] = $data;

        return $id;
    }

    public function update( string $table, array $data, array $where ): int {
        $count = 0;
        foreach ( $this->tables[ $table ] ?? [] as $index => $row ) {
            if ( $this->matches( $row, $where ) ) {
                $this->tables[ $table ][ $index ] = array_merge( $row, $data );
                ++$count;
            }
        }

        return $count;
    }

    public function delete( string $table, array $where ): int {
        $kept  = [];
        $count = 0;
        foreach ( $this->tables[ $table ] ?? [] as $row ) {
            if ( $this->matches( $row, $where ) ) {
                ++$count;
                continue;
            }
            $kept[] = $row;
        }
        $this->tables[ $table ] = $kept;

        return $count;
    }

    public function select( string $table, array $where = [], int $limit = 100, int $offset = 0, array $orderBy = [] ): array {
        $rows = [];
        foreach ( $this->tables[ $table ] ?? [] as $row ) {
            if ( $this->matches( $row, $where ) ) {
                $rows[] = $row;
            }
        }
        if ( $orderBy !== [] ) {
            usort(
                $rows,
                static function ( array $left, array $right ) use ( $orderBy ): int {
					foreach ( $orderBy as $column => $direction ) {
						$cmp = ( $left[ $column ] ?? null ) <=> ( $right[ $column ] ?? null );
						if ( $cmp !== 0 ) {
							return strtoupper( $direction ) === 'DESC' ? -$cmp : $cmp;
						}
					}

					return 0;
				}
            );
        }

        return array_slice( $rows, $offset, $limit );
    }

    public function count( string $table, array $where = [] ): int {
        return count( $this->select( $table, $where, PHP_INT_MAX, 0 ) );
    }

    public function query( string $sql ): bool {
        $this->statements[] = $sql;

        return true;
    }

    public function getResults( string $sql, array $args = [] ): array {
        $this->statements[] = $sql;

        return [];
    }

    public function getRow( string $sql, array $args = [] ): ?array {
        $this->statements[] = $sql;

        return null;
    }

    public function getVar( string $sql, array $args = [] ): mixed {
        $this->statements[] = $sql;

        return null;
    }

    /**
     * @return list<string>
     */
    public function statements(): array {
        return $this->statements;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $where
     */
    private function matches( array $row, array $where ): bool {
        foreach ( $where as $column => $value ) {
            $actual = $row[ $column ] ?? null;
            if ( $value === null ) {
                if ( $actual !== null ) {
                    return false;
                }
                continue;
            }
            if ( (string) $actual !== (string) $value ) {
                return false;
            }
        }

        return true;
    }
}
