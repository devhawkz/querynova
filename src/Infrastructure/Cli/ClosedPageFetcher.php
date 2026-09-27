<?php
/**
 * Refuses to fetch. CLI crawl only queues the first batch.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cli;

use QueryNova\Modules\Crawler\Application\FetchedPage;
use QueryNova\Modules\Crawler\Application\PageFetcher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ClosedPageFetcher implements PageFetcher {

    public function fetch( string $url ): FetchedPage {
        unset( $url );

        throw new \RuntimeException( 'The CLI does not fetch pages.' );
    }
}
