<?php
/**
 * HTTP client contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Http;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface HttpClientInterface {

    public function send( HttpRequest $request ): HttpResponse;
}
