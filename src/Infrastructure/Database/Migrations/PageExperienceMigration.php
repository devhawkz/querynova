<?php
/**
 * Adds page experience storage for installs that already ran the initial schema.
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

final class PageExperienceMigration implements MigrationInterface {

    public function version(): string {
        return '202609270002';
    }

    public function description(): string {
        return 'Create the page experience table.';
    }

    public function up( DatabaseConnection $db ): void {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_charset_collate' ) ) {
            $charset = trim( (string) $GLOBALS['wpdb']->get_charset_collate() . ' ENGINE=InnoDB' );
        }
        $sql = ( new Schema() )->statementFor( $db->prefix(), $charset, 'page_experience' );
        $sql = preg_replace( '/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $sql, 1 );
        $db->query( is_string( $sql ) ? $sql : '' );
    }
}
