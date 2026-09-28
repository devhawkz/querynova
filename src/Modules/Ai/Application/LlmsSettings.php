<?php
/**
 * llms.txt settings. The file is experimental and is not a ranking requirement.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LlmsSettings {

    public const FLAG = 'querynova_llms_txt';

    public const BODY = 'querynova_llms_body';

    public const NOTE = 'llms.txt is experimental. It is not a ranking requirement. It is served only when enabled.';

    /**
     * @return array<string, mixed>
     */
    public static function present(): array {
        $enabled = get_option( self::FLAG, 'no' ) === 'yes';
        $body    = get_option( self::BODY, '' );
        $body    = is_string( $body ) ? $body : '';
        $active  = $enabled && $body !== '';

        return [
            'enabled'             => $active,
            'experimental'        => true,
            'ranking_requirement' => false,
            'body'                => $active ? $body : null,
            'note'                => self::NOTE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( bool $enabled, string $body ): array {
        $body = trim( str_replace( "\0", '', $body ) );
        if ( ! $enabled || $body === '' ) {
            update_option( self::FLAG, 'no', false );

            return self::present() + [
                'stored' => true,
                'served' => false,
            ];
        }
        update_option( self::BODY, $body, false );
        update_option( self::FLAG, 'yes', false );
        $current           = self::present();
        $current['stored'] = true;
        $current['served'] = $current['enabled'] === true;

        return $current;
    }
}
