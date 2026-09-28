<?php
/**
 * Provider cards. Configure records a request and does not call a vendor.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProviderCards {

    public const OPTION = 'querynova_provider_configure';

    /**
     * @return array<string, mixed>
     */
    public static function present( string $name, string $state ): array {
        $name      = self::name( $name );
        $connected = $state === 'connected';

        return [
            'name'   => $name,
            'state'  => $connected ? 'Connected' : 'Not connected',
            'called' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function configure( string $name, bool $confirmed ): array {
        $name = self::name( $name );
        if ( ! $confirmed || $name === '' ) {
            return [
                'name'   => $name,
                'stored' => false,
                'called' => false,
                'note'   => 'Configure does not call a vendor. Nothing was stored.',
            ];
        }
        update_option(
            self::OPTION,
            [
                'name'   => $name,
                'called' => false,
            ],
            false
        );

        return [
            'name'   => $name,
            'stored' => true,
            'called' => false,
            'note'   => 'Configure was recorded. No vendor was called.',
        ];
    }

    private static function name( string $name ): string {
        $name = strtolower( trim( wp_strip_all_tags( $name ) ) );
        $name = preg_replace( '/[^a-z0-9_]+/', '_', $name );

        return is_string( $name ) ? trim( $name, '_' ) : '';
    }
}
