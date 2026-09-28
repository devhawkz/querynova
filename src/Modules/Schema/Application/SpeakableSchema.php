<?php
/**
 * Speakable markup only for an article that already has a headline and a CSS selector.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SpeakableSchema {

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>|null
     */
    public static function node( array $document ): ?array {
        $type = is_string( $document['type'] ?? null ) ? $document['type'] : '';
        if ( ! in_array( $type, [ 'Article', 'BlogPosting', 'article', 'post' ], true ) ) {
            return null;
        }
        $headline = self::plain( $document['headline'] ?? '' );
        $selector = self::selector( $document['selector'] ?? '' );
        if ( $headline === '' || $selector === '' ) {
            return null;
        }

        return [
            '@type'     => $type === 'BlogPosting' ? 'BlogPosting' : 'Article',
            'headline'  => $headline,
            'speakable' => [
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => [ $selector ],
            ],
        ];
    }

    private static function plain( mixed $value ): string {
        $text = trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );
        if ( strlen( $text ) > 180 ) {
            $text = substr( $text, 0, 180 );
        }

        return $text;
    }

    private static function selector( mixed $value ): string {
        $selector = trim( is_string( $value ) ? $value : '' );
        if ( preg_match( '/^[.#]?[A-Za-z][A-Za-z0-9_-]*$/', $selector ) !== 1 ) {
            return '';
        }

        return $selector;
    }
}
