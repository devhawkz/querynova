<?php
/**
 * Optional text before and after RSS content. Disabled until the user enables it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RssSupplement {

    public const OPTION = 'querynova_rss_supplement';

    /**
     * @return array{enabled: bool, before: string, after: string}
     */
    public static function read(): array {
        $stored = get_option( self::OPTION, null );
        $rows   = is_array( $stored ) ? $stored : [];

        return [
            'enabled' => ( $rows['enabled'] ?? false ) === true,
            'before'  => is_string( $rows['before'] ?? null ) ? $rows['before'] : '',
            'after'   => is_string( $rows['after'] ?? null ) ? $rows['after'] : '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{enabled: bool, before: string, after: string}
     */
    public static function save( array $input ): array {
        $stored = [
            'enabled' => ( $input['enabled'] ?? false ) === true,
            'before'  => is_string( $input['before'] ?? null ) ? self::plain( $input['before'] ) : '',
            'after'   => is_string( $input['after'] ?? null ) ? self::plain( $input['after'] ) : '',
        ];
        update_option( self::OPTION, $stored, false );

        return $stored;
    }

    public static function wrap( string $content ): string {
        $settings = self::read();
        if ( $settings['enabled'] !== true ) {
            return $content;
        }

        return $settings['before'] . $content . $settings['after'];
    }

    /**
     * Tags are removed. One leading or trailing space is kept so the text can sit beside the content.
     */
    private static function plain( string $value ): string {
        $text   = wp_strip_all_tags( $value );
        $prefix = preg_match( '/^\s/u', $value ) === 1 ? ' ' : '';
        $suffix = preg_match( '/\s$/u', $value ) === 1 ? ' ' : '';

        return $prefix . $text . $suffix;
    }
}
