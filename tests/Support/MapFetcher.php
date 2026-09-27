<?php
/**
 * Returns prepared crawl responses and records requested URLs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Support;

use QueryNova\Modules\Crawler\Application\FetchedPage;
use QueryNova\Modules\Crawler\Application\PageFetcher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MapFetcher implements PageFetcher {

    /** @var list<string> */
    public array $requested = [];

    /**
     * @param array<string, FetchedPage> $pages
     */
    public function __construct( private readonly array $pages ) {
    }

    public function fetch( string $url ): FetchedPage {
        $this->requested[] = $url;

        return $this->pages[ $url ] ?? new FetchedPage( 404, '', [] );
    }
}
