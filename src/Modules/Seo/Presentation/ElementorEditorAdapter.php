<?php
/**
 * Elementor seam. The document panel is not built.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ElementorEditorAdapter implements EditorAdapter {

    public function id(): string {
        return 'elementor';
    }

    public function label(): string {
        return 'Elementor';
    }

    public function isActive(): bool {
        return defined( 'ELEMENTOR_VERSION' );
    }

    public function isMounted(): bool {
        return false;
    }
}
