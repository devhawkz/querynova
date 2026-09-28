<?php
/**
 * AI Visibility workspace, prompt tracking, and read-only assistant tools.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Ai\Application\AiWorkspace;
use QueryNova\Modules\Ai\Application\CrawlerAccess;
use QueryNova\Modules\Ai\Application\LlmsSettings;
use QueryNova\Modules\Ai\Application\McpTools;
use QueryNova\Modules\Ai\Application\PromptTracker;
use QueryNova\Modules\Ai\AiModule;
use QueryNova\Modules\Ai\Infrastructure\NullLlmProvider;

final class AiWorkspaceTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ PromptTracker::OPTION ] );
        unset( $GLOBALS['querynova_options'][ CrawlerAccess::OPTION ] );
        unset( $GLOBALS['querynova_options'][ LlmsSettings::FLAG ] );
        unset( $GLOBALS['querynova_options'][ LlmsSettings::BODY ] );
    }

    public function testDisconnectedWorkspaceDoesNotInventCitations(): void {
        $screen = AiWorkspace::present( ( new NullLlmProvider() )->id(), [], null, null );
        $boot   = AiModule::workspaceSnapshot();

        self::assertSame( 'not_connected', $screen['provider']['status'] );
        self::assertFalse( $screen['provider']['called'] );
        self::assertNull( $screen['citations']['value'] );
        self::assertNull( $screen['mentions']['value'] );
        self::assertNull( $screen['brands']['value'] );
        self::assertNull( $screen['products']['value'] );
        self::assertNull( $screen['sentiment']['value'] );
        self::assertNull( $screen['traffic']['sessions'] );
        self::assertNull( $screen['revenue']['revenue'] );
        self::assertNull( $screen['overview']['index'] );
        self::assertFalse( $screen['cloud'] );
        self::assertSame( 'not_connected', $boot['provider']['status'] );
        self::assertNull( $boot['crawlers'][0]['access'] );
        self::assertStringContainsString( 'did not call a model', $screen['provider']['note'] );
    }

    public function testPromptTrackingStoresTheRequestedFieldsWithoutCallingAModel(): void {
        $stored = PromptTracker::track( ' best welder ', 'en_US', 'RS', 'en', 'openai', 'weekly', [ 'commerce', '<b>shop</b>' ] );
        $row    = PromptTracker::catalog()[0];

        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['called'] );
        self::assertFalse( $stored['connected'] );
        self::assertSame( 'best welder', $row['prompt'] );
        self::assertSame( 'en_US', $row['locale'] );
        self::assertSame( 'RS', $row['country'] );
        self::assertSame( 'en', $row['language'] );
        self::assertSame( 'openai', $row['provider'] );
        self::assertSame( 'weekly', $row['frequency'] );
        self::assertSame( [ 'commerce', 'shop' ], $row['tags'] );
        self::assertFalse( PromptTracker::track( ' ', 'en', 'RS', 'en', '', '', [] )['stored'] );
    }

    public function testCrawlerPreferenceAndLlmsStayOffUntilEnabled(): void {
        $held    = CrawlerAccess::plan( 'GPTBot', 'block', false );
        $stored  = CrawlerAccess::plan( 'GPTBot', 'block', true );
        $hidden  = LlmsSettings::save( false, 'Guide' );
        $visible = LlmsSettings::save( true, "Guide\n" );

        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['robots_changed'] );
        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['robots_changed'] );
        self::assertSame( 'block', CrawlerAccess::preferences()['GPTBot'] );
        self::assertSame( 'The preference is stored. robots.txt was not changed.', $stored['note'] );
        self::assertFalse( $hidden['enabled'] );
        self::assertFalse( $hidden['served'] );
        self::assertTrue( $hidden['experimental'] );
        self::assertFalse( $hidden['ranking_requirement'] );
        self::assertStringContainsString( 'not a ranking requirement', $hidden['note'] );
        self::assertTrue( $visible['enabled'] );
        self::assertTrue( $visible['experimental'] );
        self::assertFalse( $visible['ranking_requirement'] );
        self::assertStringContainsString( 'experimental', $visible['note'] );
    }

    public function testAssistantReadsOmitSecretsAndWritesNeedPermission(): void {
        $read       = McpTools::read(
            'site_status',
            [
                'environment' => 'production',
                'version'     => '0.1.2',
                'api_key'     => 'secret-value',
                'nested'      => [
                    'password' => 'hidden',
                    'label'    => 'visible',
                ],
            ]
        );
        $missing    = McpTools::read( 'rankings', [] );
        $rankings   = McpTools::read(
            'rankings',
            [
                'rankings' => [
                    [
                        'keyword'  => 'welder',
                        'position' => null,
                        'api_key'  => 'nope',
                    ],
                ],
            ]
        );
        $visibility = McpTools::read( 'ai_visibility', [] );
        $refused    = McpTools::write( 'store_note', [ 'token' => 'abc' ], false );
        $allowed    = McpTools::write( 'store_note', [ 'note' => 'later' ], true );
        $routes     = $this->routes();

        self::assertSame( 'production', $read['environment'] );
        self::assertArrayNotHasKey( 'api_key', $read );
        self::assertSame( 'unavailable', $missing['status'] );
        self::assertNull( $missing['value'] );
        self::assertStringContainsString( 'not zero', $missing['note'] );
        self::assertNull( $rankings['value'][0]['position'] );
        self::assertArrayNotHasKey( 'api_key', $rankings['value'][0] );
        self::assertNull( $visibility['value']['citations'] );
        self::assertNull( $visibility['value']['mentions'] );
        self::assertFalse( $visibility['connected'] );
        self::assertFalse( $refused['accepted'] );
        self::assertFalse( $refused['changed'] );
        self::assertTrue( $allowed['accepted'] );
        self::assertFalse( $allowed['changed'] );
        self::assertFalse( $allowed['published'] );
        self::assertContains( 'site_status', McpTools::READS );
        self::assertContains( '/mcp/read', $routes );
        self::assertContains( '/mcp/write', $routes );
        self::assertContains( '/ai/workspace', $routes );
        self::assertContains( '/ai/prompts/track', $routes );
    }

    public function testWorkspaceSourcesDoNotCallAProviderOrDrawCharts(): void {
        $files = [
            QUERYNOVA_PATH . 'src/Modules/Ai/Application/AiWorkspace.php',
            QUERYNOVA_PATH . 'src/Modules/Ai/Application/PromptTracker.php',
            QUERYNOVA_PATH . 'src/Modules/Ai/Application/CrawlerAccess.php',
            QUERYNOVA_PATH . 'src/Modules/Ai/Application/LlmsSettings.php',
            QUERYNOVA_PATH . 'src/Modules/Ai/Application/McpTools.php',
        ];
        foreach ( $files as $file ) {
            $source = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
            self::assertIsString( $source );
            self::assertStringNotContainsString( 'wp_remote_', $source );
            self::assertStringNotContainsString( 'flush_rewrite_rules', $source );
            self::assertStringNotContainsString( 'chart.js', $source );
            self::assertStringNotContainsString( 'file_put_contents', $source );
        }
    }

    /**
     * @return list<string>
     */
    private function routes(): array {
        $rest = new RestRegistrar();
        ( new AiModule() )->registerRoutes( $rest );

        return array_column( $rest->routes(), 'route' );
    }
}
