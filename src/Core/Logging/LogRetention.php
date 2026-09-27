<?php
/**
 * Log retention defaults by environment.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

use QueryNova\Core\Contracts\EnvironmentInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogRetention {

    public function days( EnvironmentInterface $environment ): int {
        if ( $environment->isProduction() ) {
            return 14;
        }
        if ( $environment->isStaging() ) {
            return 30;
        }

        return 7;
    }
}
