<?php
/**
 * Grants QueryNova capabilities without treating a nonce as authorization.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\WordPress;

use QueryNova\Core\Security\RoleMap;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CapabilityRegistrar {

    public function __construct( private readonly RoleMap $roles ) {
    }

    public function register(): void {
        foreach ( $this->roles->grants() as $roleName => $capabilities ) {
            $role = get_role( $roleName );
            if ( $role === null && str_starts_with( $roleName, 'querynova_' ) ) {
                add_role( $roleName, $this->label( $roleName ), array_fill_keys( $capabilities, true ) );
                continue;
            }
            if ( $role === null ) {
                continue;
            }
            foreach ( $capabilities as $capability ) {
                $role->add_cap( $capability );
            }
        }
        $this->refreshCurrentUser();
    }

    /**
     * Caps are copied onto the user before init. Additions in this request must be visible to REST.
     */
    private function refreshCurrentUser(): void {
        if ( ! function_exists( 'wp_get_current_user' ) ) {
            return;
        }
        $user = wp_get_current_user();
        if ( is_object( $user ) && method_exists( $user, 'get_role_caps' ) ) {
            $user->get_role_caps();
        }
    }

    private function label( string $roleName ): string {
        return match ( $roleName ) {
            'querynova_seo_manager' => 'SEO Manager',
            'querynova_content_editor' => 'Content Editor',
            'querynova_commerce_manager' => 'Commerce Manager',
            'querynova_developer' => 'Developer',
            default => $roleName,
        };
    }
}
