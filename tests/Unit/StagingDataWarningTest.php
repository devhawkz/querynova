<?php
/**
 * Staging warns only when a cloned production identifier is still stored.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Security\Capability;
use QueryNova\Modules\Core\StagingDataNotice;
use QueryNova\Modules\Core\StagingDataWarning;
use QueryNova\Modules\Core\SetupWizard;

final class StagingDataWarningTest extends TestCase {

    public function testProductionAndEmptyStagingStayQuiet(): void {
        $warning = new StagingDataWarning();
        $stored  = [
            'search_console' => [ 'property' => 'sc-domain:example.test' ],
            'ga4'            => [ 'property' => 'G-TEST' ],
        ];

        self::assertSame( [], $warning->detect( 'production', $stored )['warnings'] );
        self::assertSame( [], $warning->detect( 'staging', [] )['warnings'] );
    }

    public function testStagingNamesStoredProductionIdentifiers(): void {
        $report = ( new StagingDataWarning() )->detect(
            'staging',
            [
				'search_console' => [
					'property' => 'sc-domain:example.test',
					'state'    => 'connected',
				],
			],
            'cloud-site-1',
            'provider-project-1'
        );

        self::assertCount( 3, $report['warnings'] );
        self::assertStringContainsString( 'Search Console or GA4', $report['warnings'][0] );
        self::assertStringContainsString( 'No analytics were read', $report['warnings'][0] );
        self::assertStringContainsString( 'cloud site id', $report['warnings'][1] );
        self::assertStringContainsString( 'provider project', $report['warnings'][2] );
        self::assertStringNotContainsString( 'sc-domain:example.test', implode( ' ', $report['warnings'] ) );
    }

    public function testNoticeRequiresTheSettingsCapability(): void {
        $notice             = new StagingDataNotice( new StagingDataWarning(), new SetupWizard( new \QueryNova\Infrastructure\WordPress\OptionStore() ) );
        $report             = [
            'environment' => 'staging',
            'warnings'    => [ 'Staging has a stored Search Console or GA4 property.' ],
        ];
        $GLOBALS['qn_caps'] = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        ob_start();
        $notice->renderReport( $report );
        $hidden             = ob_get_clean();
        $GLOBALS['qn_caps'] = [ Capability::MANAGE_SETTINGS ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        ob_start();
        $notice->renderReport( $report );
        $shown = ob_get_clean();

        self::assertSame( '', $hidden );
        self::assertIsString( $shown );
        self::assertStringContainsString( 'Search Console or GA4', $shown );
    }
}
