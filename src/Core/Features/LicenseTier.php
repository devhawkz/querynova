<?php
/**
 * License tiers. This development build enables the full feature set.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Features;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum LicenseTier: string {

    case Free = 'free';
    case Pro  = 'pro';
}
