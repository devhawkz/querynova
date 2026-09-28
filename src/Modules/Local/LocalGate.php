<?php
/**
 * Local SEO stays off unless the option is exactly true.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Local;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LocalGate {

    public const OPTION = 'querynova_local_seo_enabled';

    public const LOCATIONS = 'querynova_local_locations';

    public static function enabled(): bool {
        return get_option( self::OPTION, false ) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(): array {
        $enabled = self::enabled();

        return [
            'enabled'     => $enabled,
            'locations'   => $enabled ? self::locations() : null,
            'url_changed' => false,
            'flushed'     => false,
            'note'        => $enabled
                ? 'Local SEO is enabled. Live URLs were not changed.'
                : 'Local SEO stays off until the option is exactly true. Live URLs were not changed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function save( bool $enabled, bool $confirmed ): array {
        if ( ! $confirmed ) {
            $current           = self::present();
            $current['stored'] = false;
            $current['note']   = 'Local SEO stays unchanged until you confirm. Live URLs were not changed.';

            return $current;
        }
        update_option( self::OPTION, $enabled, false );
        $current           = self::present();
        $current['stored'] = true;

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    public static function saveLocation( string $name, string $address, bool $confirmed ): array {
        $cleanName = self::text( $name );
        if ( ! self::enabled() || ! $confirmed || $cleanName === null ) {
            $current           = self::present();
            $current['stored'] = false;
            $current['note']   = 'The location stays unstored until local SEO is enabled and you confirm. Live URLs were not changed.';

            return $current;
        }
        $rows   = self::locations();
        $rows[] = [
            'name'    => $cleanName,
            'address' => self::text( $address ),
        ];
        update_option( self::LOCATIONS, $rows, false );
        $current           = self::present();
        $current['stored'] = true;
        $current['note']   = 'The location is stored. Live URLs were not changed.';

        return $current;
    }

    /**
     * @return list<array{name: string, address: ?string}>
     */
    private static function locations(): array {
        $stored = get_option( self::LOCATIONS, [] );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $rows = [];
        foreach ( $stored as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $name = self::text( $row['name'] ?? null );
            if ( $name === null ) {
                continue;
            }
            $rows[] = [
                'name'    => $name,
                'address' => self::text( $row['address'] ?? null ),
            ];
        }

        return $rows;
    }

    private static function text( mixed $value ): ?string {
        $text = trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );

        return $text === '' ? null : $text;
    }
}
