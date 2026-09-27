<?php
/**
 * XSS, SQL identifiers, and capability checks. A nonce is not a capability.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Security;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\DatabaseException;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;

final class InputSecurityTest extends TestCase {

    public function testStoredTitlesCannotKeepMarkup(): void {
        $store   = new ArrayMetaStore();
        $service = new SeoMetaService( $store, new TemplateRenderer() );
        $service->save( 'post', 3, [ SeoMetaService::TITLE => '<script>alert(1)</script>Brewery' ] );
        $title = $service->stored( 'post', 3, SeoMetaService::TITLE );

        self::assertStringNotContainsString( '<', $title );
        self::assertStringNotContainsString( '>', $title );
        self::assertStringContainsString( 'Brewery', $title );
    }

    public function testValuesStayInPlaceholdersAndBadIdentifiersAreRejected(): void {
        $wpdb            = new class() {
            /** @var list<array{0: string, 1: list<mixed>}> */
            public array $calls = [];

            public function prepare( string $sql, mixed ...$args ): string {
                $this->calls[] = [ $sql, $args ];

                return $sql;
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function get_results( string $sql, string $mode ): array {
                unset( $sql, $mode );

                return [];
            }
        };
        $GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test double for prepared SQL.
        try {
            $database = new WpdbConnection();
            $database->select( 'wp_qn_pages', [ 'title' => "'); DROP TABLE wp_users; --" ] );
            $database->select( 'wp_qn_pages; DROP TABLE wp_users', [] );
            self::fail( 'A hostile table name should be rejected.' );
        } catch ( DatabaseException ) {
            self::assertCount( 1, $wpdb->calls );
            self::assertStringContainsString( 'title = %s', $wpdb->calls[0][0] );
            self::assertSame( "'); DROP TABLE wp_users; --", $wpdb->calls[0][1][0] );
            self::assertStringNotContainsString( 'DROP TABLE', $wpdb->calls[0][0] );
        } finally {
            unset( $GLOBALS['wpdb'] );
        }
    }

    public function testANonceDoesNotGrantACapability(): void {
        $GLOBALS['qn_caps'] = [ 'wp_rest_nonce_value' ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.

        self::assertFalse( ( new RestRegistrar() )->allowed( Capability::MANAGE_SETTINGS ) );
    }
}
