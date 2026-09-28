<?php
/**
 * Virtual robots.txt. A physical file is written only when the user allows it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RobotsEditor {

    public const OPTION = 'querynova_robots_txt';

    /**
     * @return array<string, mixed>
     */
    public static function read(): array {
        $stored = get_option( self::OPTION, '' );

        return [
            'content'      => is_string( $stored ) ? $stored : '',
            'file_written' => false,
            'note'         => 'This editor stores robots.txt in QueryNova. A physical file is not changed unless you allow it.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( string $content, bool $allowFile, bool $confirm, string $path = '' ): array {
        $content = trim( wp_strip_all_tags( $content ) );
        update_option( self::OPTION, $content, false );
        if ( ! $allowFile || ! $confirm ) {
            return [
                'content'      => $content,
                'file_written' => false,
                'note'         => 'Stored in QueryNova. The physical robots.txt file was not changed.',
            ];
        }
        if ( $path === '' || ! is_file( $path ) ) {
            return [
                'content'      => $content,
                'file_written' => false,
                'note'         => 'No physical robots.txt file was found, so nothing was written.',
            ];
        }
        $written = file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the caller passed an existing path and confirmed the write.
        if ( $written === false ) {
            return [
                'content'      => $content,
                'file_written' => false,
                'note'         => 'The physical robots.txt file could not be written. The QueryNova copy is stored.',
            ];
        }

        return [
            'content'      => $content,
            'file_written' => true,
            'note'         => 'The physical robots.txt file was replaced because writing was allowed and confirmed.',
        ];
    }
}
