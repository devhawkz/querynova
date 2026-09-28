<?php
/**
 * ALT suggestions. Manual ALT text is left unchanged unless overwrite is requested.
 *
 * This class does not write attachment meta.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ImageAlt {

    /**
     * @return array<string, mixed>
     */
    public static function suggest( string $current, string $suggestion, bool $manual, bool $overwrite = false ): array {
        $current    = trim( wp_strip_all_tags( $current ) );
        $suggestion = trim( wp_strip_all_tags( $suggestion ) );
        if ( $manual && ! $overwrite ) {
            return [
                'alt'     => $current,
                'changed' => false,
                'written' => false,
                'note'    => 'Manual ALT text was left unchanged.',
            ];
        }
        if ( $suggestion === '' ) {
            return [
                'alt'     => $current,
                'changed' => false,
                'written' => false,
                'note'    => 'No ALT suggestion was supplied. The media library was not changed.',
            ];
        }

        return [
            'alt'     => $suggestion,
            'changed' => $current !== $suggestion,
            'written' => false,
            'note'    => 'This is a suggestion. Attachment ALT text was not written.',
        ];
    }
}
