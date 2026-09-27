<?php
/**
 * SEO analysis records a language context without loading WPML or Polylang.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Multilingual\LanguageResolver;
use QueryNova\Modules\Content\ContentModule;

final class LanguageResolverTest extends TestCase {

    public function testARequestLanguageWinsAndMissingPluginsStayUnknown(): void {
        $site = ( new LanguageResolver() )->context( null );
        $wpml = ( new LanguageResolver( true, 'sr', true, 'en', 'en_US' ) )->context( null );
        $poly = ( new LanguageResolver( false, null, true, null, 'en_US' ) )->context( null );

        self::assertSame( 'site', $site['provider'] );
        self::assertNull( $site['language'] );
        self::assertSame(
            [
                'provider' => 'request',
                'language' => 'sr',
            ],
            ( new LanguageResolver( true, 'en', false, null, 'en_US' ) )->context( ' sr ' )
        );
        self::assertSame( 'wpml', $wpml['provider'] );
        self::assertSame( 'sr', $wpml['language'] );
        self::assertSame( 'polylang', $poly['provider'] );
        self::assertNull( $poly['language'] );
    }

    public function testContentAnalysisCarriesTheLanguageContext(): void {
        $report = ( new ContentModule() )->inspect(
            [
                'html'     => '<p>Lager from the brewery.</p>',
                'language' => 'sr',
            ]
        );

        self::assertSame(
            [
                'provider' => 'request',
                'language' => 'sr',
            ],
            $report['language']
        );
        self::assertFalse( $report['fetched'] );
    }
}
