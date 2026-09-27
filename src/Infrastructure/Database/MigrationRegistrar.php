<?php
/**
 * Collects migrations from modules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MigrationRegistrar {

    /** @var array<string, MigrationInterface> */
    private array $migrations = [];

    public function add( MigrationInterface $migration ): void {
        $this->migrations[ $migration->version() ] = $migration;
    }

    /**
     * @return list<MigrationInterface>
     */
    public function all(): array {
        $migrations = array_values( $this->migrations );
        usort( $migrations, static fn ( MigrationInterface $a, MigrationInterface $b ): int => strcmp( $a->version(), $b->version() ) );

        return $migrations;
    }
}
