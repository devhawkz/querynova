<?php
/**
 * Video schema and sitemap markup from supplied fields. Remote pages are not fetched.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class VideoDocument {

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function build( array $fields ): array {
        $title       = self::text( $fields['title'] ?? null );
        $description = self::text( $fields['description'] ?? null );
        $content     = self::url( $fields['content_url'] ?? null );
        $embed       = self::url( $fields['embed_url'] ?? null );
        $thumbnail   = self::url( $fields['thumbnail_url'] ?? null );
        if ( $title === null || ( $content === null && $embed === null ) ) {
            return [
                'schema'  => null,
                'sitemap' => null,
                'fetched' => false,
                'saved'   => false,
                'note'    => 'Video markup needs a supplied title and a content or embed URL. Remote pages are not fetched.',
            ];
        }
        $schema = [
            '@type' => 'VideoObject',
            'name'  => $title,
        ];
        if ( $description !== null ) {
            $schema['description'] = $description;
        }
        if ( $content !== null ) {
            $schema['contentUrl'] = $content;
        }
        if ( $embed !== null ) {
            $schema['embedUrl'] = $embed;
        }
        if ( $thumbnail !== null ) {
            $schema['thumbnailUrl'] = $thumbnail;
        }

        return [
            'schema'  => $schema,
            'sitemap' => [
                'title'       => $title,
                'description' => $description,
                'content_url' => $content,
                'thumbnail'   => $thumbnail,
            ],
            'fetched' => false,
            'saved'   => false,
            'note'    => 'Video markup uses the supplied fields. Remote pages are not fetched and the sitemap was not published.',
        ];
    }

    private static function text( mixed $value ): ?string {
        $text = trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );

        return $text === '' ? null : $text;
    }

    private static function url( mixed $value ): ?string {
        $url = self::text( $value );
        if ( $url === null || preg_match( '#^https?://#i', $url ) !== 1 ) {
            return null;
        }

        return $url;
    }
}
