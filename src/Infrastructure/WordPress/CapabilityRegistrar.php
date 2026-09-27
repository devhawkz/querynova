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
