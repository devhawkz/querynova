<?php
/**
 * Schema migration.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface MigrationInterface {

    public function version(): string;

    public function description(): string;

    public function up( DatabaseConnection $db ): void;
}
