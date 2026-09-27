<?php
/**
 * Warns when another SEO plugin is loaded. It does not deactivate that plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoConflictDetector {

    /**
     * @var list<string>
     */
    public const AREAS = [ 'meta', 'schema', 'canonical', 'sitemap' ];

    /**
     * @param array<string, bool> $active
     * @return array{plugins: list<string>, warnings: list<array{plugin: string, areas: list<string>}>, disabled_other_plugin: false}
     */
    public function detect( array $active ): array {
        $names    = [
            'yoast'    => 'Yoast',
            'rankmath' => 'Rank Math',
            'aioseo'   => 'AIOSEO',
        ];
        $plugins  = [];
        $warnings = [];
        foreach ( $names as $key => $label ) {
            if ( ( $active[ $key ] ?? false ) !== true ) {
                continue;
            }
            $plugins[]  = $label;
            $warnings[] = [
                'plugin' => $label,
                'areas'  => self::AREAS,
            ];
        }

        return [
            'plugins'               => $plugins,
            'warnings'              => $warnings,
            'disabled_other_plugin' => false,
        ];
    }

    /**
     * @return array{yoast: bool, rankmath: bool, aioseo: bool}
     */
    public function activePlugins(): array {
        return [
            'yoast'    => defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ),
            'rankmath' => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ),
            'aioseo'   => defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' ),
        ];
    }
}
