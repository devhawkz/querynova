<?php
/**
 * Reports, schedules, roles, and import preview do not change live content.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Reports\Application\ReportSchedule;
use QueryNova\Modules\Reports\Application\RoleCatalog;
use QueryNova\Modules\Reports\Application\SettingsTransfer;
use QueryNova\Modules\Reports\Application\WhiteLabel;
use QueryNova\Modules\Reports\ReportModule;
use QueryNova\Modules\Seo\Application\ImportPreview;
use QueryNova\Modules\Seo\SeoModule;

final class ReportDeskTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ ReportSchedule::OPTION ] );
        unset( $GLOBALS['querynova_options'][ WhiteLabel::OPTION ] );
        unset( $GLOBALS['querynova_options'][ RoleCatalog::OPTION ] );
    }

    public function testNewReportsStayCsvOrJsonAndLeaveMissingValuesEmpty(): void {
        $module  = new ReportModule();
        $organic = $module->render( 'organic', [], 'csv' );
        $ai      = $module->render( 'ai_visibility', [], 'json' );
        $pdf     = $module->render( 'woocommerce', [ 'revenue' => 10 ], 'pdf' );
        $decoded = json_decode( (string) $ai['body'], true );

        self::assertSame( 'csv', $organic['format'] );
        self::assertStringContainsString( 'clicks', (string) $organic['body'] );
        self::assertStringNotContainsString( 'clicks,0', (string) $organic['body'] );
        self::assertNull( $decoded['values']['citations'] );
        self::assertNull( $pdf['body'] );
        self::assertSame( 'PDF is not generated.', $pdf['note'] );
        self::assertContains( 'content', $module::workspaceSnapshot()['kinds'] );
        self::assertContains( 'rank', $module::workspaceSnapshot()['kinds'] );
        self::assertContains( 'index', $module::workspaceSnapshot()['kinds'] );
        self::assertFalse( $module::workspaceSnapshot()['pdf'] );
    }

    public function testScheduleDoesNotSendMailOrStoreCustomerRecords(): void {
        $held   = ReportSchedule::plan( [ 'not-an-email', 'editor@example.com' ], 'organic', 'weekly', false );
        $stored = ReportSchedule::plan( [ 'Editor@Example.com', 'customer order 9' ], 'organic', 'weekly', true );

        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['sent'] );
        self::assertFalse( $held['customers'] );
        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['sent'] );
        self::assertFalse( $stored['customers'] );
        self::assertSame( [ 'editor@example.com' ], $stored['recipients'] );
        self::assertStringContainsString( 'does not include customer records', $stored['note'] );
    }

    public function testWhiteLabelDoesNotHideSecurityAndCustomRolesAreNotApplied(): void {
        $label = WhiteLabel::save( 'https://example.com/logo.png', 'Northwind', 'Footer', 'seo@example.com', true );
        $roles = RoleCatalog::present();
        $areas = [];
        foreach ( $roles['builtin'] as $role ) {
            $areas[ $role['role'] ] = $role['areas'];
        }
        $custom = RoleCatalog::saveCustom( 'Agency Editor', [ 'seo', 'not_an_area' ], true );

        self::assertFalse( $label['hides_security'] );
        self::assertStringContainsString( 'does not hide', $label['security'] );
        self::assertNotContains( 'seo', $areas['querynova_developer'] );
        self::assertNotContains( 'settings', $areas['querynova_content_editor'] );
        self::assertContains( 'commerce', $areas['querynova_commerce_manager'] );
        self::assertContains( 'seo', $areas['querynova_seo_manager'] );
        self::assertContains( 'debug', $areas['administrator'] );
        self::assertTrue( $custom['stored'] );
        self::assertFalse( $custom['applied'] );
        self::assertSame( [ 'seo' ], $custom['areas'] );
        self::assertFalse( RoleCatalog::saveCustom( 'Agency', [ 'seo' ], false )['stored'] );
    }

    public function testImportPreviewAndSettingsTransferDoNotWritePosts(): void {
        $preview = ImportPreview::plan(
            'yoast',
            [
                [
                    'object_type' => 'post',
                    'object_id'   => 4,
                    'meta'        => [ '_yoast_wpseo_title' => 'Welder' ],
                ],
                [
                    'object_type' => 'post',
                    'object_id'   => 5,
                    'meta'        => [],
                ],
                [
                    'object_type' => 'post',
                    'object_id'   => 0,
                    'meta'        => [ '_yoast_wpseo_title' => 'Bad' ],
                ],
            ]
        );
        $unknown = ImportPreview::plan( 'other', [ [ 'object_id' => 1 ] ] );
        $held    = SettingsTransfer::import( 'white_label', [ 'brand' => 'Northwind' ], false );
        $stored  = SettingsTransfer::import(
            'white_label',
            [
                'brand'   => 'Northwind',
                'enabled' => false,
            ],
            true
        );
        $empty   = SettingsTransfer::import( 'white_label', [], true );
        $export  = SettingsTransfer::export( 'white_label' );

        self::assertSame( 1, $preview['imported'] );
        self::assertSame( 1, $preview['skipped'] );
        self::assertSame( 1, $preview['failed'] );
        self::assertFalse( $preview['applied'] );
        self::assertFalse( $preview['disabled_other_plugin'] );
        self::assertSame( 'failed', $unknown['log'] === [] ? 'failed' : $unknown['log'][0]['status'] );
        self::assertFalse( $unknown['disabled_other_plugin'] );
        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['changed_posts'] );
        self::assertFalse( $empty['stored'] );
        self::assertFalse( $empty['changed_posts'] );
        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['changed_posts'] );
        self::assertSame( 'Northwind', $export['payload']['brand'] );
        self::assertFalse( $export['secrets'] );
    }

    public function testRoutesExistAndSourcesDoNotSendMailOrDisablePlugins(): void {
        $reports = new RestRegistrar();
        ( new ReportModule() )->registerRoutes( $reports );
        $seo = new RestRegistrar();
        ( new SeoModule() )->registerRoutes( $seo );
        $files = [
            QUERYNOVA_PATH . 'src/Modules/Reports/Application/ReportSchedule.php',
            QUERYNOVA_PATH . 'src/Modules/Reports/Application/WhiteLabel.php',
            QUERYNOVA_PATH . 'src/Modules/Reports/Application/RoleCatalog.php',
            QUERYNOVA_PATH . 'src/Modules/Seo/Application/ImportPreview.php',
        ];
        foreach ( $files as $file ) {
            $source = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
            self::assertIsString( $source );
            self::assertStringNotContainsString( 'wp_mail', $source );
            self::assertStringNotContainsString( 'deactivate_plugins', $source );
            self::assertStringNotContainsString( 'add_role', $source );
        }
        self::assertContains( '/reports/schedule', array_column( $reports->routes(), 'route' ) );
        self::assertContains( '/seo/import/preview', array_column( $seo->routes(), 'route' ) );
    }
}
