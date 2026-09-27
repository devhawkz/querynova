<?php
/**
 * Initial schema. Plugin version and schema version stay separate.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database\Migrations;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Infrastructure\Database\MigrationInterface;
use QueryNova\Infrastructure\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class InitialSchemaMigration implements MigrationInterface {

    public function version(): string {
        return '202609270001';
    }

    public function description(): string {
        return 'Create QueryNova custom tables.';
    }

    public function up( DatabaseConnection $db ): void {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_charset_collate' ) ) {
            $charset = trim( (string) $GLOBALS['wpdb']->get_charset_collate() . ' ENGINE=InnoDB' );
        }
        foreach ( ( new Schema() )->statements( $db->prefix(), $charset ) as $sql ) {
            $db->query( $sql );
        }
    }
}
