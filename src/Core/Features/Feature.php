<?php
/**
 * A gated product feature.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Features;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Feature {

    /**
     * @param list<string> $dependencies
     * @param list<string> $environments
     * @param list<string> $capabilities
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $module,
        private readonly string $version,
        private readonly array $dependencies,
        private readonly LicenseTier $licenseTier,
        private readonly array $environments,
        private readonly FeatureFlagState $defaultState,
        private readonly array $capabilities,
    ) {
    }

    public function id(): string {
        return $this->id;
    }

    public function name(): string {
        return $this->name;
    }

    public function module(): string {
        return $this->module;
    }

    public function version(): string {
        return $this->version;
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array {
        return $this->dependencies;
    }

    public function licenseTier(): LicenseTier {
        return $this->licenseTier;
    }

    /**
     * @return list<string>
     */
    public function environments(): array {
        return $this->environments;
    }

    public function defaultState(): FeatureFlagState {
        return $this->defaultState;
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array {
        return $this->capabilities;
    }
}
