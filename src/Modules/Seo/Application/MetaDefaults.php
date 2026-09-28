<?php
/**
 * Global title templates and the title separator.
 *
 * Saving writes one option. It does not rewrite post, term, or product meta.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MetaDefaults {

    public const OPTION = 'querynova_meta_defaults';

    /**
     * @return array{separator: string, templates: array<string, array{title: string, description: string}>}
     */
    public static function read(): array {
        $stored = get_option( self::OPTION, null );
        if ( ! is_array( $stored ) ) {
            return [
                'separator' => '-',
                'templates' => TitleTemplates::defaults(),
            ];
        }
        $separator = $stored['separator'] ?? '-';

        return [
            'separator' => is_string( $separator ) && trim( $separator ) !== '' ? trim( wp_strip_all_tags( $separator ) ) : '-',
            'templates' => TitleTemplates::merge( is_array( $stored['templates'] ?? null ) ? $stored['templates'] : [] ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{separator: string, templates: array<string, array{title: string, description: string}>}
     */
    public static function save( array $input ): array {
        $separator = $input['separator'] ?? '-';
        $stored    = [
            'separator' => is_string( $separator ) && trim( $separator ) !== '' ? trim( wp_strip_all_tags( $separator ) ) : '-',
            'templates' => TitleTemplates::merge( is_array( $input['templates'] ?? null ) ? $input['templates'] : [] ),
        ];
        update_option( self::OPTION, $stored, false );

        return $stored;
    }

    /**
     * @return array<string, string>
     */
    public static function templateSet( string $context ): array {
        $defaults = self::read();
        $row      = $defaults['templates'][ $context ] ?? TitleTemplates::defaults()['post'];

        return [
            $context                  => (string) $row['title'],
            $context . '_description' => (string) $row['description'],
        ];
    }
}
