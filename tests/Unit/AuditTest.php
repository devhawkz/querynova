<?php
/**
 * Audit history stores a hash of the IP and rolls back only title and description.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Audit\AuditModule;
use QueryNova\Modules\Audit\Infrastructure\AuditRepository;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;

final class AuditTest extends TestCase {

    public function testRawIpIsNotStored(): void {
        $database = new ArrayDatabase();
        $module   = new AuditModule( new AuditRepository( $database ), new ArrayMetaStore() );
        $recorded = $module->record(
            [
                'user_id'     => 7,
                'action'      => 'seo_changed',
                'object_type' => 'product',
                'object_id'   => 4,
                'before'      => [ 'title' => 'Old title' ],
                'after'       => [ 'title' => 'New title' ],
                'ip'          => '203.0.113.10',
                'environment' => 'production',
            ]
        );
        $row      = $database->select( 'wp_qn_audit_log', [ 'id' => $recorded['id'] ], 1 )[0];

        self::assertSame( hash( 'sha256', '203.0.113.10' ), $row['ip_hash'] );
        self::assertStringNotContainsString( '203.0.113.10', (string) wp_json_encode( $row ) );
        self::assertSame( [], $database->select( 'wp_qn_logs', [], 5 ) );
    }

    public function testCanonicalIsNotRolledBack(): void {
        $meta   = new ArrayMetaStore();
        $module = new AuditModule( new AuditRepository( new ArrayDatabase() ), $meta );
        $meta->set( 'product', 4, 'canonical', 'https://shop.example/current' );
        $recorded = $module->record(
            [
                'action'      => 'canonical_changed',
                'object_type' => 'product',
                'object_id'   => 4,
                'before'      => [ 'canonical' => 'https://shop.example/old' ],
                'after'       => [ 'canonical' => 'https://shop.example/current' ],
            ]
        );
        $result   = $module->rollback( (int) $recorded['id'] );

        self::assertFalse( $result['applied'] );
        self::assertSame( 'https://shop.example/current', $meta->get( 'product', 4, 'canonical' ) );
    }

    public function testTitleRollbackRestoresThePreviousValue(): void {
        $meta   = new ArrayMetaStore();
        $module = new AuditModule( new AuditRepository( new ArrayDatabase() ), $meta );
        $meta->set( 'product', 4, 'title', 'New title' );
        $recorded = $module->record(
            [
                'user_id'     => 3,
                'action'      => 'seo_changed',
                'object_type' => 'product',
                'object_id'   => 4,
                'before'      => [ 'title' => 'Old title' ],
                'after'       => [ 'title' => 'New title' ],
            ]
        );
        $result   = $module->rollback( (int) $recorded['id'] );

        self::assertTrue( $result['applied'] );
        self::assertSame( 'Old title', $meta->get( 'product', 4, 'title' ) );
        self::assertSame( [ 'title' ], $result['fields'] );
    }

    public function testUnknownActionIsRejected(): void {
        $this->expectException( ValidationException::class );
        ( new AuditModule() )->record( [ 'action' => 'deleted_everything' ] );
    }

    public function testSafeModeOmitsAudit(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'audit', $names );
        self::assertNotContains(
            'audit',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
