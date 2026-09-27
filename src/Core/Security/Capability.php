<?php
/**
 * Capability identifiers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Capability {

    public const MANAGE_SETTINGS        = 'querynova_manage_settings';
    public const VIEW_ANALYTICS         = 'querynova_view_analytics';
    public const MANAGE_SEO             = 'querynova_manage_seo';
    public const MANAGE_WOOCOMMERCE_SEO = 'querynova_manage_woocommerce_seo';
    public const RUN_ANALYSIS           = 'querynova_run_analysis';
    public const MANAGE_INTEGRATIONS    = 'querynova_manage_integrations';
    public const VIEW_LOGS              = 'querynova_view_logs';
    public const MANAGE_DEBUG           = 'querynova_manage_debug';

    /**
     * @return list<string>
     */
    public static function all(): array {
        return [
            self::MANAGE_SETTINGS,
            self::VIEW_ANALYTICS,
            self::MANAGE_SEO,
            self::MANAGE_WOOCOMMERCE_SEO,
            self::RUN_ANALYSIS,
            self::MANAGE_INTEGRATIONS,
            self::VIEW_LOGS,
            self::MANAGE_DEBUG,
        ];
    }
}
