<?php
/**
 * Admin warning for another SEO plugin. The other plugin stays active.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Security\Capability;
use QueryNova\Modules\Seo\Application\SeoConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoConflictNotice implements HookSubscriberInterface {

    public function __construct( private readonly SeoConflictDetector $detector ) {
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
        $this->renderFrom( $this->detector->activePlugins() );
    }

    /**
     * @param array<string, bool> $active
     */
    public function renderFrom( array $active ): void {
        if ( ! current_user_can( Capability::MANAGE_SEO ) ) {
            return;
        }
        $report = $this->detector->detect( $active );
        if ( $report['plugins'] === [] ) {
            return;
        }
        $message = sprintf(
            /* translators: %s: other SEO plugin names */
            __( 'QueryNova found %s. Meta, schema, canonical, and sitemap output may be duplicated. QueryNova did not disable the other plugin.', 'querynova' ),
            implode( ', ', $report['plugins'] )
        );
        echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
    }
}
