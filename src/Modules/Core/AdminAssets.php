<?php
/**
 * Enqueues the admin application only on QueryNova screens.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Core\Contracts\HookSubscriberInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminAssets implements HookSubscriberInterface {

    public function hooks(): array {
        return [
            'admin_enqueue_scripts' => 'enqueue',
        ];
    }

    public function hookType( string $hook ): string {
        unset( $hook );

        return 'action';
    }

    public function enqueue( string $page ): void {
        if ( ! str_contains( $page, 'querynova' ) ) {
            return;
        }

        $path = QUERYNOVA_PATH . 'build/admin.js';
        if ( ! is_file( $path ) ) {
            add_action(
                'admin_notices',
                static function (): void {
                    echo '<div class="notice notice-warning"><p>' . esc_html__( 'QueryNova admin assets are missing. Run npm run build inside the plugin directory.', 'querynova' ) . '</p></div>';
                }
            );

            return;
        }

        $briefing = $this->briefing();
        wp_enqueue_script( 'querynova-admin', QUERYNOVA_URL . 'build/admin.js', [], QUERYNOVA_VERSION, true );
        wp_add_inline_script(
            'querynova-admin',
            'window.querynovaAdmin = ' . wp_json_encode(
                [
                    'restUrl'           => esc_url_raw( rest_url( 'querynova/v1' ) ),
                    'nonce'             => wp_create_nonce( 'wp_rest' ),
                    'version'           => QUERYNOVA_VERSION,
                    'environment'       => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
                    'wooCommerceActive' => class_exists( 'WooCommerce' ),
                    'actions'           => $briefing['actions'],
                    'sections'          => $briefing['sections'],
                ]
            ) . ';',
            'before'
        );
    }

    /**
     * Stored rows only. A read failure leaves every section empty.
     *
     * @return array{actions: list<array<string, mixed>>, sections: array<string, list<array<string, mixed>>>}
     */
    private function briefing(): array {
        $empty = [
            'actions'  => [],
            'sections' => DashboardBriefing::emptySections(),
        ];
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return $empty;
        }
        try {
            return ( new DashboardBriefing() )->fromDatabase( new \QueryNova\Infrastructure\Database\WpdbConnection() );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return $empty;
        }
    }
}
