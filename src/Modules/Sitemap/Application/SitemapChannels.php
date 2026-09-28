<?php
/**
 * Sitemap channel settings. A missing option leaves the public sitemap unchanged.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapChannels {

    public const OPTION = 'querynova_sitemap_channels';

    /**
     * @return list<string>
     */
    public static function keys(): array {
        return [ 'post', 'page', 'cpt', 'taxonomy', 'product', 'brand', 'image', 'author', 'html', 'news', 'video', 'kml' ];
    }

    public static function localEnabled(): bool {
        return get_option( 'querynova_local_seo_enabled', false ) === true;
    }

    /**
     * @param list<string> $current
     * @return list<string>
     */
    public static function limit( array $current, bool $localEnabled, string $newsName ): array {
        $stored = get_option( self::OPTION, null );
        if ( ! is_array( $stored ) ) {
            return $current;
        }
        $flags = self::flags( $stored, $localEnabled, $newsName );
        $kept  = [];
        foreach ( $current as $type ) {
            if ( ( $flags[ $type ] ?? true ) === true ) {
                $kept[] = $type;
            }
        }

        return $kept;
    }

    /**
     * @return array<string, mixed>
     */
    public static function read( bool $localEnabled, string $newsName ): array {
        $stored = get_option( self::OPTION, null );
        $flags  = self::flags( is_array( $stored ) ? $stored : self::defaults(), $localEnabled, $newsName );

        return [
            'saved'    => is_array( $stored ),
            'channels' => $flags,
            'news'     => $newsName === '' ? 'off' : 'configured',
            'kml'      => $localEnabled ? 'available' : 'off',
            'note'     => 'News stays off until a publication name is stored. KML stays off until local SEO is enabled. Nothing is written until you save.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function save( array $input, bool $localEnabled, string $newsName ): array {
        $flags = self::flags( is_array( $input['channels'] ?? null ) ? $input['channels'] : [], $localEnabled, $newsName );
        update_option( self::OPTION, $flags, false );
        $read          = self::read( $localEnabled, $newsName );
        $read['saved'] = true;
        $read['note']  = 'Channel settings are stored. Existing post content was not rewritten.';

        return $read;
    }

    /**
     * @param list<string> $urls
     */
    public static function html( array $urls ): string {
        $items = '';
        foreach ( $urls as $url ) {
            if ( ! is_string( $url ) || preg_match( '#^https?://#', $url ) !== 1 ) {
                continue;
            }
            $safe   = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
            $items .= '<li><a href="' . $safe . '">' . $safe . '</a></li>';
        }

        return '<ul class="querynova-html-sitemap">' . $items . '</ul>';
    }

    /**
     * @param list<array{name: string, latitude: float, longitude: float}> $places
     * @return array<string, mixed>
     */
    public static function kml( array $places, bool $localEnabled ): array {
        if ( ! $localEnabled ) {
            return [
                'kml'  => '',
                'note' => 'KML stays off until local SEO is enabled.',
            ];
        }
        $marks = '';
        foreach ( $places as $place ) {
            $name   = htmlspecialchars( (string) ( $place['name'] ?? '' ), ENT_QUOTES, 'UTF-8' );
            $lat    = (float) ( $place['latitude'] ?? 0 );
            $lng    = (float) ( $place['longitude'] ?? 0 );
            $marks .= '<Placemark><name>' . $name . '</name><Point><coordinates>' . $lng . ',' . $lat . ',0</coordinates></Point></Placemark>';
        }

        return [
            'kml'  => '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document>' . $marks . '</Document></kml>',
            'note' => 'KML is generated from the supplied places. Permalinks were not flushed.',
        ];
    }

    /**
     * @return array<string, bool>
     */
    private static function defaults(): array {
        return [
            'post'     => true,
            'page'     => true,
            'cpt'      => true,
            'taxonomy' => true,
            'product'  => true,
            'brand'    => true,
            'image'    => true,
            'author'   => false,
            'html'     => false,
            'news'     => false,
            'video'    => true,
            'kml'      => false,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, bool>
     */
    private static function flags( array $input, bool $localEnabled, string $newsName ): array {
        $flags = self::defaults();
        foreach ( self::keys() as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $flags[ $key ] = $input[ $key ] === true;
            }
        }
        if ( $newsName === '' ) {
            $flags['news'] = false;
        }
        if ( ! $localEnabled ) {
            $flags['kml'] = false;
        }

        return $flags;
    }
}
