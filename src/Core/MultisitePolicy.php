<?php
/**
 * Multisite scope. QueryNova stores data for the current site.
 *
 * There is no network settings screen. Network activation still prepares only
 * the site WordPress is activating, using that site's table prefix.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MultisitePolicy {

    public function networkManagement(): bool {
        return false;
    }

    public function activatesCurrentSite( bool $networkWide ): bool {
        if ( $this->networkManagement() ) {
            return ! $networkWide;
        }

        return true;
    }

    public function registersSiteMenu(): bool {
        return ! ( function_exists( 'is_network_admin' ) && is_network_admin() );
    }
}
