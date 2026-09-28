<?php
/**
 * Global link settings. Defaults stay off and nothing is inserted.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LinkSettings {

    public const OPTION = 'querynova_link_settings';

    /**
     * @return array<string, mixed>
     */
    public static function present(): array {
        $stored = get_option( self::OPTION, [] );
        $stored = is_array( $stored ) ? $stored : [];
        $saved  = $stored !== [];

        return [
            'new_tab'     => ( $stored['new_tab'] ?? false ) === true,
            'nofollow'    => ( $stored['nofollow'] ?? false ) === true,
            'auto_insert' => ( $stored['auto_insert'] ?? false ) === true,
            'applied'     => false,
            'inserted'    => false,
            'note'        => $saved
                ? 'Global link settings are stored. Nothing is inserted.'
                : 'New tab, nofollow, and automatic insertion stay off. Nothing is inserted.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( bool $newTab, bool $nofollow, bool $autoInsert, bool $confirmed ): array {
        if ( ! $confirmed ) {
            $current           = self::present();
            $current['stored'] = false;
            $current['note']   = 'Global link settings stay unchanged until you confirm. Nothing is inserted.';

            return $current;
        }
        update_option(
            self::OPTION,
            [
                'new_tab'     => $newTab,
                'nofollow'    => $nofollow,
                'auto_insert' => $autoInsert,
            ],
            false
        );
        $current           = self::present();
        $current['stored'] = true;

        return $current;
    }
}
