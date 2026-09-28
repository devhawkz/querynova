<?php
/**
 * A document editor surface.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface EditorAdapter {

    public function id(): string;

    public function label(): string;

    /**
     * The integration is available in this request.
     */
    public function isActive(): bool;

    /**
     * QueryNova renders its panel inside this surface.
     */
    public function isMounted(): bool;
}
