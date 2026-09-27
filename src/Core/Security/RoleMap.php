<?php
/**
 * Role to capability map.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RoleMap {

    /**
     * @return array<string, list<string>>
     */
    public function grants(): array {
        return [
            'administrator'              => Capability::all(),
            'querynova_seo_manager'      => [
                Capability::MANAGE_SETTINGS,
                Capability::VIEW_ANALYTICS,
                Capability::MANAGE_SEO,
                Capability::MANAGE_WOOCOMMERCE_SEO,
                Capability::RUN_ANALYSIS,
                Capability::MANAGE_INTEGRATIONS,
                Capability::VIEW_LOGS,
            ],
            'querynova_content_editor'   => [
                Capability::MANAGE_SEO,
                Capability::RUN_ANALYSIS,
            ],
            'querynova_commerce_manager' => [
                Capability::VIEW_ANALYTICS,
                Capability::MANAGE_WOOCOMMERCE_SEO,
                Capability::RUN_ANALYSIS,
            ],
            'querynova_developer'        => [
                Capability::MANAGE_SETTINGS,
                Capability::VIEW_LOGS,
                Capability::MANAGE_DEBUG,
                Capability::RUN_ANALYSIS,
            ],
        ];
    }
}
