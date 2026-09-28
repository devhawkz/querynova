<?php
/**
 * Non-blocking notice when the installed build and WordPress environment differ.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Core\BuildChannel;
use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\ReleaseProfile;
use QueryNova\Core\Security\Capability;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BuildChannelNotice implements HookSubscriberInterface {

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
        if ( ! current_user_can( Capability::MANAGE_SETTINGS ) ) {
            return;
        }
        $markup = $this->markup( $this->profile() );
        if ( $markup === '' ) {
            return;
        }

        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes the notice text.
    }

    public function markup( ReleaseProfile $profile ): string {
        $notice = $profile->notice();
        if ( $notice === null ) {
            return '';
        }
        $class = $profile->status() === ReleaseProfile::STATUS_WARNING ? 'notice notice-warning' : 'notice notice-info';

        return '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $notice ) . '</p></div>';
    }

    private function profile(): ReleaseProfile {
        return ReleaseProfile::assess( ( new WordPressEnvironment() )->getName(), BuildChannel::installedChannel() );
    }
}
