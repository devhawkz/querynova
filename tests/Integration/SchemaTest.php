<?php
/**
 * Schema coverage for the specification's minimum tables.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\Schema;

final class SchemaTest extends TestCase {

    public function testRequiredTablesAreCreated(): void {
        $sql = implode( "\n", ( new Schema() )->statements( 'wp_', 'ENGINE=InnoDB' ) );
        foreach ( Schema::requiredTables() as $table ) {
            self::assertStringContainsString( 'CREATE TABLE wp_qn_' . $table . ' (', $sql );
        }
        self::assertStringContainsString( 'PRIMARY KEY (id)', $sql );
        self::assertStringContainsString( 'KEY metric_date', $sql );
        self::assertStringContainsString( 'KEY product_id', $sql );
    }
}
