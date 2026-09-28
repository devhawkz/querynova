<?php
/**
 * QueryNova notices for the admin shell. This does not add a WordPress admin notice.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NotificationCenter {

    /**
     * @param list<string> $staging
     * @param list<string> $plugins
     * @return array<string, mixed>
     */
    public static function collect( array $staging, ?string $releaseNotice, string $releaseStatus, array $plugins ): array {
        $items = [];
        foreach ( $staging as $index => $warning ) {
            $text = self::text( $warning );
            if ( $text === null ) {
                continue;
            }
            $items[] = [
                'id'      => 'staging-' . $index,
                'level'   => 'warning',
                'message' => $text,
            ];
        }
        $release = self::text( $releaseNotice );
        if ( $release !== null ) {
            $items[] = [
                'id'      => 'build',
                'level'   => $releaseStatus === 'warning' ? 'warning' : 'info',
                'message' => $release,
            ];
        }
        $names = [];
        foreach ( $plugins as $plugin ) {
            $name = self::text( $plugin );
            if ( $name !== null ) {
                $names[] = $name;
            }
        }
        if ( $names !== [] ) {
            $items[] = [
                'id'      => 'seo-conflict',
                'level'   => 'warning',
                'message' => sprintf(
                    /* translators: %s: other SEO plugin names */
                    __( 'QueryNova found %s. Meta, schema, canonical, and sitemap output may be duplicated. QueryNova did not disable the other plugin.', 'querynova' ),
                    implode( ', ', $names )
                ),
            ];
        }

        return [
            'items'              => $items,
            'hides_security'     => false,
            'added_admin_notice' => false,
            'note'               => 'These notices stay inside QueryNova. WordPress security notices stay visible. No new admin notice was added.',
        ];
    }

    private static function text( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $text = trim( wp_strip_all_tags( $value ) );

        return $text === '' ? null : $text;
    }
}
