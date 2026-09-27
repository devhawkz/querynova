<?php
/**
 * Environment contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface EnvironmentInterface {

    public function getName(): string;

    public function isLocal(): bool;

    public function isDevelopment(): bool;

    public function isStaging(): bool;

    public function isProduction(): bool;

    public function allowsVerboseDiagnostics(): bool;
}
