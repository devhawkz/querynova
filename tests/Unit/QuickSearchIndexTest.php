<?php
/**
 * Quick search reads stored titles and does not write.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Modules\Core\QuickSearchIndex;

final class QuickSearchIndexTest extends TestCase {

    public function testCatalogStaysEmptyWhenWordPressPostsAreUnavailable(): void {
        self::assertFalse( function_exists( 'get_posts' ) );

        $catalog = QuickSearchIndex::catalog();

        self::assertSame( [], $catalog['posts'] );
        self::assertSame( [], $catalog['pages'] );
        self::assertFalse( $catalog['fetched'] );
        self::assertFalse( $catalog['written'] );
        self::assertFalse( $catalog['called'] );
    }

    public function testPresentKeepsTwentyTitlesAndDropsEmptyOnes(): void {
        $posts = [];
        for ( $index = 1; $index <= 25; $index++ ) {
            $posts[] = [
                'ID'         => $index,
                'post_title' => 'Post ' . (string) $index,
            ];
        }
        $posts[] = [
            'id'    => 90,
            'title' => '',
        ];
        $posts[] = [
            'id'    => 0,
            'title' => 'Missing id',
        ];

        $catalog = QuickSearchIndex::present(
            $posts,
            [
                [
                    'id'    => 4,
                    'title' => '<strong>About</strong>',
                ],
                'not-a-post',
            ]
        );

        self::assertCount( QuickSearchIndex::LIMIT, $catalog['posts'] );
        self::assertSame( 'Post 1', $catalog['posts'][0]['title'] );
        self::assertSame( 'Post 20', $catalog['posts'][19]['title'] );
        self::assertSame(
            [
                [
                    'id'    => 4,
                    'title' => 'About',
                ],
            ],
            $catalog['pages']
        );
        self::assertFalse( $catalog['fetched'] );
        self::assertFalse( $catalog['written'] );
        self::assertFalse( $catalog['called'] );
        self::assertStringContainsString( 'does not call a provider', $catalog['note'] );
    }

    public function testTheIndexDoesNotWriteOrCallOut(): void {
        $source = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/QuickSearchIndex.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        $boot   = file_get_contents( QUERYNOVA_PATH . 'src/Modules/Core/AdminAssets.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the test reads plugin source, not a remote URL.
        self::assertIsString( $source );
        self::assertIsString( $boot );
        self::assertStringNotContainsString( 'update_post_meta(', $source );
        self::assertStringNotContainsString( 'update_option(', $source );
        self::assertStringNotContainsString( 'wp_remote_', $source );
        self::assertStringContainsString( "'documents'", $boot );
        self::assertStringContainsString( 'QuickSearchIndex::catalog()', $boot );
    }
}
