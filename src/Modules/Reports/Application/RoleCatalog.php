<?php
/**
 * Built-in roles by area, plus one stored custom role. WordPress roles are not rewritten.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Reports\Application;

use QueryNova\Core\Security\Capability;
use QueryNova\Core\Security\RoleMap;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RoleCatalog {

    public const OPTION = 'querynova_custom_roles';

    /**
     * @var list<string>
     */
    public const AREAS = [ 'settings', 'analytics', 'seo', 'commerce', 'analysis', 'logs', 'debug', 'integrations' ];

    /**
     * @return array<string, mixed>
     */
    public static function present(): array {
        $builtin = [];
        foreach ( ( new RoleMap() )->grants() as $role => $capabilities ) {
            $builtin[] = [
                'role'    => $role,
                'areas'   => self::areas( $capabilities ),
                'applied' => false,
            ];
        }

        return [
            'builtin' => $builtin,
            'custom'  => self::custom(),
            'note'    => 'Built-in roles are shown from the fixed map. A custom role is stored only. WordPress roles were not changed.',
        ];
    }

    /**
     * @param list<string> $areas
     * @return array<string, mixed>
     */
    public static function saveCustom( string $name, array $areas, bool $confirmed ): array {
        $name  = self::name( $name );
        $areas = self::chosen( $areas );
        if ( ! $confirmed || $name === '' || $areas === [] ) {
            return [
                'stored'  => false,
                'applied' => false,
                'note'    => 'A custom role is not stored until you confirm a name and at least one area. WordPress roles were not changed.',
            ];
        }
        update_option(
            self::OPTION,
            [
                'name'  => $name,
                'areas' => $areas,
            ],
            false
        );

        return [
            'stored'  => true,
            'applied' => false,
            'name'    => $name,
            'areas'   => $areas,
            'note'    => 'The custom role is stored. WordPress roles were not changed.',
        ];
    }

    /**
     * @return array{name: string, areas: list<string>}|null
     */
    private static function custom(): ?array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return null;
        }
        $name  = self::name( is_string( $stored['name'] ?? null ) ? $stored['name'] : '' );
        $areas = self::chosen( is_array( $stored['areas'] ?? null ) ? $stored['areas'] : [] );
        if ( $name === '' || $areas === [] ) {
            return null;
        }

        return [
            'name'  => $name,
            'areas' => $areas,
        ];
    }

    /**
     * @param list<string> $capabilities
     * @return list<string>
     */
    private static function areas( array $capabilities ): array {
        $map   = [
            Capability::MANAGE_SETTINGS        => 'settings',
            Capability::VIEW_ANALYTICS         => 'analytics',
            Capability::MANAGE_SEO             => 'seo',
            Capability::MANAGE_WOOCOMMERCE_SEO => 'commerce',
            Capability::RUN_ANALYSIS           => 'analysis',
            Capability::VIEW_LOGS              => 'logs',
            Capability::MANAGE_DEBUG           => 'debug',
            Capability::MANAGE_INTEGRATIONS    => 'integrations',
        ];
        $areas = [];
        foreach ( $map as $capability => $area ) {
            if ( in_array( $capability, $capabilities, true ) ) {
                $areas[] = $area;
            }
        }

        return $areas;
    }

    /**
     * @param list<mixed> $areas
     * @return list<string>
     */
    private static function chosen( array $areas ): array {
        $clean = [];
        foreach ( $areas as $area ) {
            if ( is_string( $area ) && in_array( $area, self::AREAS, true ) && ! in_array( $area, $clean, true ) ) {
                $clean[] = $area;
            }
        }

        return $clean;
    }

    private static function name( string $name ): string {
        $name = strtolower( trim( wp_strip_all_tags( $name ) ) );
        $name = preg_replace( '/[^a-z0-9_]+/', '_', $name );
        $name = is_string( $name ) ? trim( $name, '_' ) : '';
        if ( strlen( $name ) > 40 ) {
            $name = substr( $name, 0, 40 );
        }

        return $name;
    }
}
