<?php
/**
 * Gutenberg, classic, and WooCommerce surfaces that this build mounts.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BuiltinEditorAdapter implements EditorAdapter {

    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly bool $active,
        private readonly bool $mounted,
    ) {
    }

    public function id(): string {
        return $this->id;
    }

    public function label(): string {
        return $this->label;
    }

    public function isActive(): bool {
        return $this->active;
    }

    public function isMounted(): bool {
        return $this->mounted;
    }
}
