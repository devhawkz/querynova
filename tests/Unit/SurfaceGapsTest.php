<?php
/**
 * Podcast, post-list columns, speakable, and link settings stay off until they are valid.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Content\Application\LinkSettings;
use QueryNova\Modules\Content\ContentModule;
use QueryNova\Modules\Podcast\PodcastGate;
use QueryNova\Modules\Podcast\PodcastModule;
use QueryNova\Modules\Schema\Application\SpeakableSchema;
use QueryNova\Modules\Schema\Domain\SchemaTypes;
use QueryNova\Modules\Seo\Application\PostListColumns;
use QueryNova\Modules\Seo\Presentation\PostListColumnsSubscriber;
use QueryNova\Modules\Seo\SeoModule;

final class SurfaceGapsTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_options'][ PodcastGate::OPTION ] );
        unset( $GLOBALS['querynova_options'][ LinkSettings::OPTION ] );
    }

    public function testPodcastLoadsOnlyWhenTheOptionIsExactlyTrue(): void {
        self::assertNotContains( 'podcast', $this->names( false ) );
        self::assertNotContains( 'podcast', $this->names( true ) );
        $GLOBALS['querynova_options'][ PodcastGate::OPTION ] = 'true';
        self::assertFalse( PodcastGate::enabled() );
        self::assertNotContains( 'podcast', $this->names( false ) );
        $GLOBALS['querynova_options'][ PodcastGate::OPTION ] = true;
        self::assertContains( 'podcast', $this->names( false ) );
        self::assertNotContains( 'podcast', $this->names( true ) );

        $off = new RestRegistrar();
        unset( $GLOBALS['querynova_options'][ PodcastGate::OPTION ] );
        ( new PodcastModule() )->registerRoutes( $off );
        self::assertNotContains( '/podcast/status', array_column( $off->routes(), 'route' ) );

        $GLOBALS['querynova_options'][ PodcastGate::OPTION ] = true;
        $on = new RestRegistrar();
        ( new PodcastModule() )->registerRoutes( $on );
        $status = PodcastGate::present();
        $held   = PodcastGate::save( true, false );

        self::assertContains( '/podcast/status', array_column( $on->routes(), 'route' ) );
        self::assertNull( $status['episodes'] );
        self::assertFalse( $status['published'] );
        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['published'] );
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Podcast/PodcastModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        self::assertIsString( $source );
        self::assertStringNotContainsString( 'flush_rewrite_rules', $source );
    }

    public function testQuickEditWritesOnlyFourFieldsAndOnlyWhenMetaExists(): void {
        $columns = PostListColumns::columns(
            [
                'cb'    => '<input />',
                'title' => 'Title',
            ]
        );
        $edited  = PostListColumns::quickEdit(
            8,
            [
                'title'       => 'Welder',
                'content'     => 'Body',
                'slug'        => 'welder',
                'description' => 'A guide',
            ],
            true
        );
        $held    = PostListColumns::quickEdit( 8, [ 'title' => 'Welder' ], false );
        $hooks   = ( new PostListColumnsSubscriber() )->hooks();
        ( new PostListColumnsSubscriber() )->savePost( 8 );

        self::assertArrayHasKey( 'title', $columns );
        self::assertArrayHasKey( 'querynova_title', $columns );
        self::assertArrayHasKey( 'querynova_description', $columns );
        self::assertArrayHasKey( 'querynova_indexability', $columns );
        self::assertArrayHasKey( 'querynova_focus_keyword', $columns );
        self::assertSame( [ 'title', 'description' ], $edited['fields'] );
        self::assertFalse( $edited['written'] );
        self::assertFalse( $held['written'] );
        self::assertArrayHasKey( 'manage_posts_columns', $hooks );
        self::assertArrayHasKey( 'quick_edit_custom_box', $hooks );
        self::assertFalse( function_exists( 'update_post_meta' ) );
    }

    public function testSpeakableStaysOutUnlessAnArticleHasAHeadlineAndSelector(): void {
        $node = SpeakableSchema::node(
            [
                'type'     => 'post',
                'headline' => 'Lager',
                'selector' => '.entry',
            ]
        );
        self::assertIsArray( $node );
        self::assertSame( 'SpeakableSpecification', $node['speakable']['@type'] );
        self::assertSame( [ '.entry' ], $node['speakable']['cssSelector'] );
        self::assertNull(
            SpeakableSchema::node(
                [
                    'type'     => 'Article',
                    'headline' => 'Lager',
                ]
            )
        );
        self::assertNull(
            SpeakableSchema::node(
                [
                    'type'     => 'Product',
                    'headline' => 'Lager',
                    'selector' => '.entry',
                ]
            )
        );
        self::assertNull(
            SpeakableSchema::node(
                [
                    'type'     => 'Article',
                    'headline' => 'Lager',
                    'selector' => '<script>',
                ]
            )
        );
        self::assertNotContains( 'Speakable', SchemaTypes::all() );
        self::assertNotContains( 'SpeakableSpecification', SchemaTypes::all() );
    }

    public function testLinkSettingsDefaultOffAndDoNotInsert(): void {
        $missing = LinkSettings::present();
        $held    = LinkSettings::save( true, true, true, false );
        $stored  = LinkSettings::save( true, false, true, true );
        $routes  = new RestRegistrar();
        ( new ContentModule() )->registerRoutes( $routes );
        ( new SeoModule() )->registerRoutes( $routes );

        self::assertFalse( $missing['new_tab'] );
        self::assertFalse( $missing['nofollow'] );
        self::assertFalse( $missing['auto_insert'] );
        self::assertFalse( $missing['applied'] );
        self::assertFalse( $missing['inserted'] );
        self::assertFalse( $held['stored'] );
        self::assertFalse( $held['new_tab'] );
        self::assertTrue( $stored['stored'] );
        self::assertTrue( $stored['new_tab'] );
        self::assertTrue( $stored['auto_insert'] );
        self::assertFalse( $stored['applied'] );
        self::assertFalse( $stored['inserted'] );
        self::assertContains( '/content/link-settings', array_column( $routes->routes(), 'route' ) );
        self::assertContains( '/podcast', array_column( $routes->routes(), 'route' ) );
    }

    /**
     * @return list<string>
     */
    private function names( bool $safeMode ): array {
        $names = [];
        foreach ( ModuleCatalog::modules( $safeMode ) as $module ) {
            $names[] = $module->getName();
        }

        return $names;
    }
}
