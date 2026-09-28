<?php
/**
 * Podcast stays off unless the option is exactly true.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Podcast;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PodcastGate {

    public const OPTION = 'querynova_podcast_enabled';

    public static function enabled(): bool {
        return get_option( self::OPTION, false ) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(): array {
        $enabled = self::enabled();

        return [
            'enabled'   => $enabled,
            'episodes'  => null,
            'published' => false,
            'note'      => $enabled
                ? 'Podcast is enabled. Nothing was published.'
                : 'Podcast stays off until the option is exactly true. Nothing was published.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( bool $enabled, bool $confirmed ): array {
        if ( ! $confirmed ) {
            $current           = self::present();
            $current['stored'] = false;
            $current['note']   = 'Podcast stays unchanged until you confirm. Nothing was published.';

            return $current;
        }
        update_option( self::OPTION, $enabled, false );
        $current           = self::present();
        $current['stored'] = true;

        return $current;
    }
}
