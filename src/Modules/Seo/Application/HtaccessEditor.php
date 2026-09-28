<?php
/**
 * .htaccess editor. Hidden on Nginx. A write needs Advanced mode, a backup, and confirmation.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HtaccessEditor {

    public const OPTION = 'querynova_htaccess_backup';

    /**
     * @return array<string, mixed>
     */
    public static function visibility( string $server, bool $advanced ): array {
        if ( stripos( $server, 'nginx' ) !== false ) {
            return [
                'visible' => false,
                'note'    => 'Hidden on Nginx.',
            ];
        }
        if ( ! $advanced ) {
            return [
                'visible' => false,
                'note'    => 'Open Advanced to edit .htaccess.',
            ];
        }

        return [
            'visible' => true,
            'note'    => 'A backup is stored before any write. Confirm is required.',
        ];
    }

    public static function serverSoftware(): string {
        $server = filter_input( INPUT_SERVER, 'SERVER_SOFTWARE' );

        return is_string( $server ) ? $server : '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( string $content, string $server, bool $advanced, bool $confirm, bool $writeFile, string $path = '' ): array {
        $visibility = self::visibility( $server, $advanced );
        $content    = trim( wp_strip_all_tags( $content ) );
        if ( $visibility['visible'] !== true ) {
            return [
                'written' => false,
                'backup'  => null,
                'note'    => $visibility['note'],
            ];
        }
        if ( ! $confirm ) {
            return [
                'written' => false,
                'backup'  => null,
                'note'    => 'Confirm the write before QueryNova changes .htaccess.',
            ];
        }
        $previous = get_option( self::OPTION, '' );
        $backup   = is_string( $previous ) ? $previous : '';
        if ( $path !== '' && is_file( $path ) ) {
            $file = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local backup of a file the caller confirmed.
            if ( is_string( $file ) ) {
                $backup = $file;
            }
        }
        update_option( self::OPTION, $backup, false );
        if ( ! $writeFile || $path === '' || ! is_file( $path ) ) {
            return [
                'written' => false,
                'backup'  => $backup,
                'note'    => 'A backup is stored. The physical .htaccess file was not changed.',
            ];
        }
        $written = file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the caller confirmed a write to an existing file.

        return [
            'written' => $written !== false,
            'backup'  => $backup,
            'note'    => $written === false
                ? 'The backup is stored. The physical .htaccess file could not be written.'
                : 'The physical .htaccess file was written after backup and confirmation.',
        ];
    }
}
