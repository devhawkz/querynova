<?php
/**
 * Feature flag states.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Features;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum FeatureFlagState: string {

    case On           = 'ON';
    case Off          = 'OFF';
    case Experimental = 'EXPERIMENTAL';
}
