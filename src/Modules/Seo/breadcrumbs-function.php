<?php
/**
 * Public breadcrumb function. An empty crumb list prints nothing.
 *
 * @package QueryNova
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'querynova_breadcrumbs' ) ) {
    /**
     * @param list<array{label: string, url: string}> $crumbs
     */
    function querynova_breadcrumbs( array $crumbs = [] ): string {
        return \QueryNova\Modules\Seo\Application\Breadcrumbs::html( $crumbs );
    }
}
