<?php
/**
 * AI observations stay empty until a provider runs, and the index is not an official rank.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Ai\AiModule;
use QueryNova\Modules\Ai\Application\AiVisibility;
use QueryNova\Modules\Ai\Domain\AiObservation;
use QueryNova\Modules\Ai\Infrastructure\AiRepository;
use QueryNova\Modules\Ai\Infrastructure\NullLlmProvider;
use QueryNova\Modules\Ai\Presentation\LlmsFrontend;

final class AiTest extends TestCase {

    public function testDisconnectedProviderIsNotAMention(): void {
        self::assertNull( ( new NullLlmProvider() )->observe( 'best welder', 'en' ) );
        $commerce = ( new AiVisibility() )->commerce( null );

        self::assertSame( 'UNAVAILABLE', $commerce['status'] );
        self::assertNull( $commerce['brand_share'] );
        self::assertNull( $commerce['product_mentions'] );
        self::assertSame( AiVisibility::DISCLAIMER, $commerce['disclaimer'] );
    }

    public function testIndexStaysNullUntilEveryInputExists(): void {
        $visibility = new AiVisibility();
        $missing    = $visibility->index(
            [
                'mention_coverage'      => 1.0,
                'citation_coverage'     => 1.0,
                'unique_urls'           => null,
                'crawler_accessibility' => 1.0,
                'entity_clarity'        => 1.0,
                'competitor_share'      => 1.0,
            ]
        );
        $ready      = $visibility->index(
            [
                'mention_coverage'      => 1.0,
                'citation_coverage'     => 0.0,
                'unique_urls'           => 0.5,
                'crawler_accessibility' => 1.0,
                'entity_clarity'        => 1.0,
                'competitor_share'      => 0.5,
            ]
        );

        self::assertNull( $missing['value'] );
        self::assertSame( AiVisibility::DISCLAIMER, $missing['disclaimer'] );
        self::assertSame( 'ESTIMATED', $ready['status'] );
        self::assertSame( 'LOW', $ready['confidence'] );
        self::assertSame( AiVisibility::DISCLAIMER, $ready['disclaimer'] );
        self::assertStringNotContainsString( 'rank #', (string) wp_json_encode( $ready ) );
    }

    public function testCommercialCoverageNeedsCommercialPrompts(): void {
        $rows = ( new AiVisibility() )->commerce(
            [
                [
                    'brand'       => true,
                    'product'     => false,
                    'cited'       => false,
                    'commercial'  => false,
                    'competitors' => [ 'Rival' ],
                ],
            ]
        );

        self::assertSame( 1.0, $rows['brand_share'] );
        self::assertSame( 0, $rows['product_mentions'] );
        self::assertNull( $rows['commercial_coverage'] );
        self::assertSame( [ 'Rival' ], $rows['competitor_presence'] );
    }

    public function testReferralOmissionIsNotZero(): void {
        $visibility = new AiVisibility();
        $missing    = $visibility->referrals( null );
        $partial    = $visibility->referrals(
            [
                [
                    'sessions' => 4,
                    'orders'   => 1,
                    'revenue'  => null,
                ],
            ]
        );
        $empty      = $visibility->referrals( [] );

        self::assertNull( $missing['sessions'] );
        self::assertNull( $partial['revenue'] );
        self::assertSame( 0, $empty['sessions'] );
        self::assertNull( $empty['cvr'] );
    }

    public function testCrawlerAuditDoesNotChangeRobots(): void {
        $report = ( new AiModule() )->inspect(
            'best welder',
            'en',
            null,
            [],
            "User-agent: GPTBot\nDisallow: /\n",
            [
                [
                    'title' => 'Welders',
                    'url'   => 'https://shop.example/welders',
                ],
            ],
            false
        );
        $gpt    = null;
        foreach ( $report['crawlers'] as $crawler ) {
            if ( $crawler['user_agent'] === 'GPTBot' ) {
                $gpt = $crawler;
            }
        }

        self::assertFalse( $gpt['access'] );
        self::assertNull( $gpt['last_verified'] );
        self::assertFalse( $report['robots_changed'] );
        self::assertNull( $report['llms'] );
        self::assertSame( 0, $report['stored_runs'] );
        self::assertFalse( ( new LlmsFrontend() )->enabled() );
    }

    public function testEnabledLlmsDocumentIsNotARankingClaim(): void {
        $body = ( new \QueryNova\Modules\Ai\Application\LlmsDocument() )->body(
            [
                [
                    'title' => 'Welders',
                    'url'   => 'https://shop.example/welders',
                ],
            ],
            true
        );

        self::assertIsString( $body );
        self::assertStringContainsString( 'not a proven search ranking solution', (string) $body );
        self::assertStringContainsString( 'https://shop.example/welders', (string) $body );
    }

    public function testStoredObservationDoesNotInventARank(): void {
        $database = new ArrayDatabase();
        $store    = new AiRepository( $database );
        $promptId = $store->savePrompt( 'best welder', 'en', 'fixture', 'fixture' );
        $store->saveRun(
            $promptId,
            'fixture',
            'fixture',
            new AiObservation( true, true, true, 'https://shop.example/welder', [ 'Rival' ], [], 'Acme welder is mentioned.' )
        );
        $run = $store->runs()[0];

        self::assertSame( 'complete', $run['status'] );
        self::assertSame( 1, $run['brand_mentioned'] );
        self::assertArrayNotHasKey( 'rank', $run );
        self::assertSame( 'citation', $database->select( 'wp_qn_ai_mentions', [ 'mention_type' => 'citation' ], 1 )[0]['mention_type'] );
    }

    public function testModuleDoesNotCallAModelOnTheFrontend(): void {
        $source = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Ai/AiModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.
        $nulls  = (string) file_get_contents( QUERYNOVA_PATH . 'src/Modules/Ai/Infrastructure/NullLlmProvider.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin source, not a remote request.

        self::assertStringNotContainsString( 'wp_remote_', $source . $nulls );
        self::assertStringNotContainsString( 'rank #', $source . $nulls );
    }

    public function testSafeModeOmitsAi(): void {
        $names = array_map(
            static function ( $module ): string {
                return $module->getName();
            },
            ModuleCatalog::modules( false )
        );

        self::assertContains( 'ai', $names );
        self::assertNotContains(
            'ai',
            array_map(
                static function ( $module ): string {
                    return $module->getName();
                },
                ModuleCatalog::modules( true )
            )
        );
    }
}
