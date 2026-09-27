<?php
/**
 * Circular module dependency.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Modules;

use QueryNova\Core\Exceptions\ConfigurationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CircularDependencyException extends ConfigurationException {

    /**
     * @param list<string> $path
     */
    public function __construct( array $path, string $repeated ) {
        $cycle = implode( ' -> ', array_merge( $path, [ $repeated ] ) );
        parent::__construct( 'Circular module dependency: ' . $cycle );
    }
}
