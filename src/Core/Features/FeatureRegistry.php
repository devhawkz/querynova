<?php
/**
 * Feature catalogue and flag resolution.
 *
 * Resolution order: site override, then environment override, then default.
 * Plan gating is recorded on the feature. This build keeps every implemented
 * feature available; a later license adapter can force OFF without a rewrite.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Features;

use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Exceptions\ConfigurationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FeatureRegistry {

    /** @var array<string, Feature> */
    private array $features = [];

    /** @var array<string, FeatureFlagState> */
    private array $siteOverrides = [];

    /** @var array<string, array<string, FeatureFlagState>> */
    private array $environmentOverrides = [];

    public function register( Feature $feature ): void {
        if ( isset( $this->features[ $feature->id() ] ) ) {
            throw new ConfigurationException( sprintf( 'Feature "%s" is already registered.', $feature->id() ) );
        }
        $this->features[ $feature->id() ] = $feature;
    }

    public function overrideForSite( string $id, FeatureFlagState $state ): void {
        $this->assertKnown( $id );
        $this->siteOverrides[ $id ] = $state;
    }

    public function overrideForEnvironment( string $id, string $environment, FeatureFlagState $state ): void {
        $this->assertKnown( $id );
        $this->environmentOverrides[ $environment ][ $id ] = $state;
    }

    public function state( string $id, EnvironmentInterface $environment ): FeatureFlagState {
        $this->assertKnown( $id );
        if ( isset( $this->siteOverrides[ $id ] ) ) {
            return $this->siteOverrides[ $id ];
        }
        $name = $environment->getName();
        if ( isset( $this->environmentOverrides[ $name ][ $id ] ) ) {
            return $this->environmentOverrides[ $name ][ $id ];
        }

        return $this->features[ $id ]->defaultState();
    }

    public function isEnabled( string $id, EnvironmentInterface $environment ): bool {
        $state = $this->state( $id, $environment );
        if ( $state === FeatureFlagState::Off ) {
            return false;
        }
        $feature = $this->features[ $id ];
        if ( $feature->environments() !== [] && ! in_array( $environment->getName(), $feature->environments(), true ) ) {
            return false;
        }
        foreach ( $feature->dependencies() as $dependency ) {
            if ( ! $this->isEnabled( $dependency, $environment ) ) {
                return false;
            }
        }

        return true;
    }

    public function get( string $id ): Feature {
        $this->assertKnown( $id );

        return $this->features[ $id ];
    }

    /**
     * @return array<string, Feature>
     */
    public function all(): array {
        return $this->features;
    }

    private function assertKnown( string $id ): void {
        if ( ! isset( $this->features[ $id ] ) ) {
            throw new ConfigurationException( sprintf( 'Unknown feature "%s".', $id ) );
        }
    }
}
