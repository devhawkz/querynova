<?php
/**
 * Discovers, validates, and orders modules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Modules;

use QueryNova\Core\Contracts\ModuleInterface;
use QueryNova\Core\Exceptions\ConfigurationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ModuleRegistry {

    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, \Throwable> */
    private array $failures = [];

    public function add( ModuleInterface $module ): void {
        $name = $module->getName();
        if ( isset( $this->modules[ $name ] ) ) {
            throw new ConfigurationException( sprintf( 'Module "%s" is already registered.', $name ) );
        }
        $this->modules[ $name ] = $module;
    }

    public function has( string $name ): bool {
        return isset( $this->modules[ $name ] );
    }

    public function get( string $name ): ModuleInterface {
        if ( ! isset( $this->modules[ $name ] ) ) {
            throw new ConfigurationException( sprintf( 'Module "%s" is not registered.', $name ) );
        }

        return $this->modules[ $name ];
    }

    /**
     * @return array<string, ModuleInterface>
     */
    public function all(): array {
        return $this->modules;
    }

    /**
     * @return list<ModuleInterface>
     */
    public function bootOrder(): array {
        $this->assertDependenciesExist();

        return $this->sort();
    }

    public function recordFailure( string $module, \Throwable $exception ): void {
        $this->failures[ $module ] = $exception;
    }

    public function failure( string $module ): ?\Throwable {
        return $this->failures[ $module ] ?? null;
    }

    /**
     * @return array<string, \Throwable>
     */
    public function failures(): array {
        return $this->failures;
    }

    private function assertDependenciesExist(): void {
        foreach ( $this->modules as $module ) {
            foreach ( $module->getDependencies() as $dependency ) {
                if ( ! isset( $this->modules[ $dependency ] ) ) {
                    throw new ConfigurationException(
                        sprintf( 'Module "%s" depends on missing module "%s".', $module->getName(), $dependency )
                    );
                }
            }
        }
    }

    /**
     * @return list<ModuleInterface>
     */
    private function sort(): array {
        $visited = [];
        $stack   = [];
        $ordered = [];

        $visit = function ( string $name, array $path ) use ( &$visit, &$visited, &$stack, &$ordered ): void {
            if ( isset( $stack[ $name ] ) ) {
                throw new CircularDependencyException( $path, $name );
            }
            if ( isset( $visited[ $name ] ) ) {
                return;
            }

            $stack[ $name ] = true;
            foreach ( $this->modules[ $name ]->getDependencies() as $dependency ) {
                $visit( $dependency, array_merge( $path, [ $name ] ) );
            }
            unset( $stack[ $name ] );
            $visited[ $name ] = true;
            $ordered[]        = $this->modules[ $name ];
        };

        foreach ( array_keys( $this->modules ) as $name ) {
            $visit( $name, [] );
        }

        return $ordered;
    }
}
