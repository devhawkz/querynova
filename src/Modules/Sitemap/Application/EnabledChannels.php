<?php
/**
 * Sitemap channels that are allowed to be published.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Application;

use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Features\FeatureRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EnabledChannels {

    /**
     * @return list<string>
     */
    public function types( FeatureRegistry $features, EnvironmentInterface $environment, string $newsName ): array {
        $types = [ 'post', 'page', 'product', 'category', 'brand', 'cpt', 'taxonomy', 'image', 'video' ];
        if ( $newsName !== '' && $features->isEnabled( 'querynova.sitemap.news', $environment ) ) {
            $types[] = 'news';
        }

        return $types;
    }
}
