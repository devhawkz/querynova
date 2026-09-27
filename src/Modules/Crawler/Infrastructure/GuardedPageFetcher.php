<?php
/**
 * Fetches one URL after the SSRF check. Redirects are not followed.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Infrastructure;

use QueryNova\Core\Exceptions\ProviderException;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Security\SsrfGuard;
use QueryNova\Infrastructure\Http\HttpClientInterface;
use QueryNova\Infrastructure\Http\HttpRequest;
use QueryNova\Modules\Crawler\Application\FetchedPage;
use QueryNova\Modules\Crawler\Application\PageFetcher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class GuardedPageFetcher implements PageFetcher {

    public function __construct(
        private readonly SsrfGuard $guard,
        private readonly HttpClientInterface $http,
    ) {
    }

    public function fetch( string $url ): FetchedPage {
        try {
            $this->guard->assertSafe( $url );
            $response = $this->http->send(
                new HttpRequest(
                    'GET',
                    $url,
                    [ 'User-Agent' => 'QueryNova Crawler' ],
                    null,
                    10,
                    0
                )
            );
        } catch ( ValidationException | ProviderException $exception ) {
            return new FetchedPage( 0, '', [], $exception->getMessage() );
        }

        return new FetchedPage( $response->status, $response->body, $response->headers );
    }
}
