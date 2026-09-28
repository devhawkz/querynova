<?php
/**
 * Content drafts stay unpublished, and a missing model stays not connected.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Content\Application\ContentAutomation;
use QueryNova\Modules\Content\Application\ContentDrafts;
use QueryNova\Modules\Content\Application\ContentIntelligence;
use QueryNova\Modules\Content\Application\ContentWorkspace;
use QueryNova\Modules\Content\ContentModule;

final class ContentDraftTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ ContentDrafts::MODEL ] );
        unset( $GLOBALS['querynova_options'][ ContentDrafts::OPTION ] );
        unset( $GLOBALS['querynova_options'][ ContentAutomation::OPTION ] );
    }

    public function testMissingModelStaysNotConnectedAndDoesNotCreateADraft(): void {
        $connection = ContentDrafts::connection();
        $generated  = ContentDrafts::generate( 'outline', 'A supplied outline' );

        self::assertSame( 'not_connected', $connection['status'] );
        self::assertFalse( $connection['called'] );
        self::assertStringContainsString( 'Not connected', ucfirst( str_replace( '_', ' ', $connection['status'] ) ) );
        self::assertStringContainsString( 'did not call a model', $connection['note'] );
        self::assertSame( 'not_connected', $generated['status'] );
        self::assertFalse( $generated['stored'] );
        self::assertFalse( $generated['published'] );
        self::assertFalse( $generated['page_changed'] );
        self::assertFalse( $generated['called'] );
        self::assertSame( [], ContentDrafts::recent() );
    }

    public function testStoredDraftIsNotPublishedAndGenerateDoesNotCallAModel(): void {
        $saved     = ContentDrafts::saveModel( ' local-writer ' );
        $stored    = ContentDrafts::store( 'titles', '<b>Shop welder</b>' );
        $generated = ContentDrafts::generate( 'faq', 'What size welder?' );
        $empty     = ContentDrafts::generate( 'metas', ' ' );
        $snapshot  = ContentModule::workspaceSnapshot();

        self::assertSame( 'connected', $saved['status'] );
        self::assertFalse( $saved['called'] );
        self::assertSame( 'local-writer', $saved['model'] );
        self::assertTrue( $stored['stored'] );
        self::assertFalse( $stored['published'] );
        self::assertFalse( $stored['page_changed'] );
        self::assertFalse( $stored['called'] );
        self::assertSame( 'Shop welder', ContentDrafts::recent()[1]['text'] );
        self::assertFalse( ContentDrafts::recent()[0]['published'] );
        self::assertSame( 'not_called', $generated['status'] );
        self::assertTrue( $generated['stored'] );
        self::assertFalse( $generated['published'] );
        self::assertStringContainsString( 'did not call the model', $generated['note'] );
        self::assertFalse( $empty['stored'] );
        self::assertFalse( $snapshot['published'] );
        self::assertFalse( $snapshot['page_changed'] );
        self::assertSame( 'Supply a document. QueryNova does not fetch the URL.', $snapshot['brief']['lead'] );
    }

    public function testOneRuleCanBeAutomatedAndTheOtherStaysSuggestOnly(): void {
        $off      = ContentAutomation::apply( 'internal_links', false );
        $keyword  = ContentAutomation::apply( 'keyword_links', true );
        $replaced = ContentAutomation::apply( 'internal_links', true );
        $modes    = [];
        foreach ( $replaced['rules'] as $row ) {
            $modes[ $row['rule'] ] = $row;
        }

        self::assertSame( 'Suggestions stay Suggest Only. Nothing is inserted.', $off['note'] );
        self::assertSame( 'Suggest Only', $off['rules'][0]['mode'] );
        self::assertFalse( $off['rules'][0]['inserted'] );
        self::assertSame( 'Automated', $keyword['rules'][1]['mode'] );
        self::assertSame( 'Suggest Only', $keyword['rules'][0]['mode'] );
        self::assertFalse( $keyword['rules'][1]['applied'] );
        self::assertFalse( $keyword['rules'][1]['page_changed'] );
        self::assertSame( 'Automated', $modes['internal_links']['mode'] );
        self::assertSame( 'Suggest Only', $modes['keyword_links']['mode'] );
        self::assertFalse( $modes['internal_links']['inserted'] );
    }

    public function testGapStaysUnavailableWithoutTopResults(): void {
        $engine   = new ContentIntelligence();
        $coverage = $engine->coverage( '<p>Amperage guide</p>', [ 'amperage' ], [], [] );
        $missing  = $engine->gap( $coverage );
        $covered  = $engine->gap( $engine->coverage( '<p>Duty cycle</p>', [ 'amperage', 'duty cycle' ], [ 'amperage is listed' ], [] ) );
        $empty    = ContentWorkspace::present( null );
        $routes   = $this->routes();

        self::assertSame( 'UNAVAILABLE', $missing['status'] );
        self::assertNull( $missing['topics'] );
        self::assertStringContainsString( 'did not crawl', $missing['note'] );
        self::assertSame( 'MEASURED', $covered['status'] );
        self::assertSame( [ 'amperage' ], $covered['topics'] );
        self::assertNull( $empty['gap']['topics'] );
        self::assertContains( '/content/drafts', $routes );
        self::assertContains( '/content/model', $routes );
        self::assertContains( '/content/automation', $routes );
        self::assertContains( '/content/workspace', $routes );
    }

    public function testDraftSourcesDoNotCallAModelOrPublish(): void {
        $files = [
            QUERYNOVA_PATH . 'src/Modules/Content/Application/ContentDrafts.php',
            QUERYNOVA_PATH . 'src/Modules/Content/Application/ContentAutomation.php',
            QUERYNOVA_PATH . 'src/Modules/Content/ContentModule.php',
        ];
        foreach ( $files as $file ) {
            $source = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
            self::assertIsString( $source );
            self::assertStringNotContainsString( 'wp_remote_', $source );
            self::assertStringNotContainsString( 'wp_insert_post', $source );
            self::assertStringNotContainsString( 'wp_update_post', $source );
            self::assertStringNotContainsString( 'chart.js', $source );
        }
    }

    /**
     * @return list<string>
     */
    private function routes(): array {
        $rest = new RestRegistrar();
        ( new ContentModule() )->registerRoutes( $rest );

        return array_column( $rest->routes(), 'route' );
    }
}
