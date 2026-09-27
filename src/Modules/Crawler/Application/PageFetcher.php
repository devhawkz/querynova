<?php
/**
 * Fetches one URL. Implementations must not follow the rest of the site.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface PageFetcher {

    public function fetch( string $url ): FetchedPage;
}
