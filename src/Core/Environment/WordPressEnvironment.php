<?php
/**
 * WordPress environment via wp_get_environment_type().
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Environment;

use QueryNova\Core\Contracts\EnvironmentInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressEnvironment implements EnvironmentInterface {

    public function getName(): string {
        $name    = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
        $allowed = [ 'local', 'development', 'staging', 'production' ];
        if ( ! in_array( $name, $allowed, true ) ) {
            return 'production';
        }

        return $name;
    }

    public function isLocal(): bool {
        return $this->getName() === 'local';
    }

    public function isDevelopment(): bool {
        return $this->getName() === 'development';
    }

    public function isStaging(): bool {
        return $this->getName() === 'staging';
    }

    public function isProduction(): bool {
        return $this->getName() === 'production';
    }

    public function allowsVerboseDiagnostics(): bool {
        return ! $this->isProduction();
    }
}
