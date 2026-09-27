<?php
/**
 * Admin warning for staging sites that still store production identifiers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Security\Capability;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class StagingDataNotice implements HookSubscriberInterface {

    public function __construct(
        private readonly StagingDataWarning $warning,
        private readonly SetupWizard $setup,
    ) {
    }

    public function hooks(): array {
        return [
            'admin_notices' => 'render',
        ];
    }

    public function hookType( string $hook ): string {
        unset( $hook );

        return 'action';
    }

    public function render(): void {
        $environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
        $this->renderReport( $this->warning->detect( $environment, $this->setup->read( class_exists( 'WooCommerce' ) ) ) );
    }

    /**
     * @param array{environment: string, warnings: list<string>} $report
     */
    public function renderReport( array $report ): void {
        if ( ! current_user_can( Capability::MANAGE_SETTINGS ) || $report['warnings'] === [] ) {
            return;
        }
        echo '<div class="notice notice-warning">';
        foreach ( $report['warnings'] as $warning ) {
            echo '<p>' . esc_html( $warning ) . '</p>';
        }
        echo '</div>';
    }
}
