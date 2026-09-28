<?php
/**
 * Optional white label. WordPress security information stays visible.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Reports\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WhiteLabel {

    public const OPTION = 'querynova_white_label';

    public const SECURITY = 'WordPress security notices stay visible. White label does not hide them.';

    /**
     * @return array<string, mixed>
     */
    public static function present(): array {
        $stored = get_option( self::OPTION, [] );
        $stored = is_array( $stored ) ? $stored : [];

        return [
            'enabled'        => ( $stored['enabled'] ?? false ) === true,
            'logo'           => self::plain( $stored['logo'] ?? '' ),
            'brand'          => self::plain( $stored['brand'] ?? '' ),
            'footer'         => self::plain( $stored['footer'] ?? '' ),
            'sender'         => self::email( $stored['sender'] ?? '' ),
            'hides_security' => false,
            'security'       => self::SECURITY,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( string $logo, string $brand, string $footer, string $sender, bool $enabled ): array {
        update_option(
            self::OPTION,
            [
                'enabled' => $enabled,
                'logo'    => self::plain( $logo ),
                'brand'   => self::plain( $brand ),
                'footer'  => self::plain( $footer ),
                'sender'  => self::email( $sender ),
            ],
            false
        );
        $current           = self::present();
        $current['stored'] = true;

        return $current;
    }

    private static function plain( mixed $value ): string {
        $text = trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );
        if ( strlen( $text ) > 180 ) {
            $text = substr( $text, 0, 180 );
        }

        return $text;
    }

    private static function email( mixed $value ): string {
        $email = strtolower( trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) ) );
        if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) === false ) {
            return '';
        }

        return $email;
    }
}
