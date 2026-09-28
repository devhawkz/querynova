<?php
/**
 * Webmaster verification codes. Empty codes print nothing.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WebmasterCodes {

    public const OPTION = 'querynova_webmaster_codes';

    /**
     * @return array<string, string>
     */
    public static function read(): array {
        $stored = get_option( self::OPTION, null );
        $rows   = is_array( $stored ) ? $stored : [];
        $codes  = [];
        foreach ( [ 'google', 'bing', 'pinterest', 'yandex' ] as $engine ) {
            $codes[ $engine ] = is_string( $rows[ $engine ] ?? null ) ? trim( wp_strip_all_tags( $rows[ $engine ] ) ) : '';
        }

        return $codes;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function save( array $input ): array {
        $codes = [];
        foreach ( [ 'google', 'bing', 'pinterest', 'yandex' ] as $engine ) {
            $codes[ $engine ] = is_string( $input[ $engine ] ?? null ) ? trim( wp_strip_all_tags( $input[ $engine ] ) ) : '';
        }
        update_option( self::OPTION, $codes, false );

        return $codes;
    }

    /**
     * @param array<string, string> $codes
     */
    public static function meta( array $codes ): string {
        $html = '';
        foreach ( $codes as $engine => $code ) {
            if ( $code === '' ) {
                continue;
            }
            $html .= '<meta name="' . esc_attr( (string) $engine . '-site-verification' ) . '" content="' . esc_attr( $code ) . '" />' . "\n";
        }

        return $html;
    }
}
