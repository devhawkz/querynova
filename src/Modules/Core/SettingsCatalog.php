<?php
/**
 * Read-only settings snapshot for the admin screen.
 *
 * The catalog describes registries. It does not write options, role caps,
 * robots files, image text, or live URLs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Modules\ModuleRegistry;
use QueryNova\Core\Security\RoleMap;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsCatalog {

    /**
     * @return array<string, mixed>
     */
    public static function fromContainer( ContainerInterface $container, EnvironmentInterface $environment, string $build ): array {
        $modules  = $container->has( ModuleRegistry::class ) ? $container->get( ModuleRegistry::class ) : null;
        $features = $container->has( FeatureRegistry::class ) ? $container->get( FeatureRegistry::class ) : null;
        if ( ! $modules instanceof ModuleRegistry || ! $features instanceof FeatureRegistry ) {
            return self::payload( $environment, $build, [], self::roles() );
        }

        return self::describe( $modules, $features, $environment, $build );
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe( ModuleRegistry $modules, FeatureRegistry $features, EnvironmentInterface $environment, string $build ): array {
        $grouped = [];
        foreach ( $features->all() as $feature ) {
            $state                           = $features->state( $feature->id(), $environment );
            $grouped[ $feature->module() ][] = [
                'id'           => $feature->id(),
                'name'         => $feature->name(),
                'module'       => $feature->module(),
                'state'        => $state->value,
                'enabled'      => $features->isEnabled( $feature->id(), $environment ),
                'experimental' => $state === FeatureFlagState::Experimental,
            ];
        }

        $rows = [];
        foreach ( $modules->all() as $module ) {
            $name   = $module->getName();
            $rows[] = [
                'name'         => $name,
                'version'      => $module->getVersion(),
                'optional'     => $module->isOptional(),
                'dependencies' => array_values( $module->getDependencies() ),
                'failed'       => $modules->failure( $name ) !== null,
                'registered'   => true,
                'features'     => $grouped[ $name ] ?? [],
            ];
            unset( $grouped[ $name ] );
        }

        foreach ( $grouped as $name => $feature_rows ) {
            $rows[] = [
                'name'         => (string) $name,
                'version'      => '',
                'optional'     => true,
                'dependencies' => [],
                'failed'       => false,
                'registered'   => false,
                'features'     => $feature_rows,
            ];
        }

        return self::payload( $environment, $build, $rows, self::roles() );
    }

    /**
     * @param list<array<string, mixed>> $modules
     * @param list<array<string, mixed>> $roles
     * @return array<string, mixed>
     */
    private static function payload( EnvironmentInterface $environment, string $build, array $modules, array $roles ): array {
        return [
            'wordpress_environment' => $environment->getName(),
            'querynova_build'       => $build,
            'modules'               => $modules,
            'roles'                 => $roles,
        ];
    }

    /**
     * @return list<array{role: string, capabilities: list<string>}>
     */
    private static function roles(): array {
        $roles = [];
        foreach ( ( new RoleMap() )->grants() as $role => $capabilities ) {
            $roles[] = [
                'role'         => $role,
                'capabilities' => array_values( $capabilities ),
            ];
        }

        return $roles;
    }
}
