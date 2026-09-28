<?php
/**
 * Product and category base requests. Live permalinks are not rewritten.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class UrlBaseRewrite {

    public const OPTION = 'querynova_commerce_url_bases';

    /**
     * @return array<string, mixed>
     */
    public static function plan( string $productBase, string $categoryBase, bool $confirmed ): array {
        $productBase  = trim( wp_strip_all_tags( $productBase ) );
        $categoryBase = trim( wp_strip_all_tags( $categoryBase ) );
        if ( ! $confirmed ) {
            return [
                'applied'       => false,
                'flushed'       => false,
                'stored'        => false,
                'product_base'  => $productBase,
                'category_base' => $categoryBase,
                'note'          => 'Product and category URL bases stay unchanged until you confirm.',
            ];
        }
        update_option(
            self::OPTION,
            [
                'product_base'  => $productBase,
                'category_base' => $categoryBase,
            ],
            false
        );

        return [
            'applied'       => false,
            'flushed'       => false,
            'stored'        => true,
            'product_base'  => $productBase,
            'category_base' => $categoryBase,
            'note'          => 'The request is stored. Live product and category URLs were not changed.',
        ];
    }
}
