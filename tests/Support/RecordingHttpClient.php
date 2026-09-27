<?php
/**
 * Records one outgoing request and does not perform I/O.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Support;

use QueryNova\Infrastructure\Http\HttpClientInterface;
use QueryNova\Infrastructure\Http\HttpRequest;
use QueryNova\Infrastructure\Http\HttpResponse;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RecordingHttpClient implements HttpClientInterface {

    public int $calls = 0;

    public HttpRequest $request;

    public function send( HttpRequest $request ): HttpResponse {
        ++$this->calls;
        $this->request = $request;

        return new HttpResponse( 204, '' );
    }
}
