<?php
/**
 * WordPress HTTP API client. Logs status only, never authorization headers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Http;

use QueryNova\Core\Exceptions\ProviderException;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Logging\LogChannel;
use QueryNova\Core\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressHttpClient implements HttpClientInterface {

    public function __construct(
        private readonly CorrelationContext $correlation,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function send( HttpRequest $request ): HttpResponse {
        $args = [
            'method'      => strtoupper( $request->method ),
            'timeout'     => $request->timeout,
            'headers'     => array_merge(
                $request->headers,
                [
					'X-QueryNova-Correlation' => $this->correlation->correlationId(),
				]
            ),
            'redirection' => 3,
        ];
        if ( $request->json !== null ) {
            $encoded                         = wp_json_encode( $request->json );
            $args['body']                    = is_string( $encoded ) ? $encoded : '{}';
            $args['headers']['Content-Type'] = 'application/json';
        }

        $result = wp_remote_request( $request->url, $args );
        if ( is_wp_error( $result ) ) {
            $this->logger?->warning(
                'HTTP request failed.',
                [
                    'channel'  => LogChannel::PROVIDERS,
                    'url_host' => self::host( $request->url ),
                ]
            );
            throw new ProviderException( 'The provider request failed.' );
        }

        $status = (int) wp_remote_retrieve_response_code( $result );
        $body   = (string) wp_remote_retrieve_body( $result );
        $this->logger?->info(
            'HTTP request completed.',
            [
                'channel'  => LogChannel::PROVIDERS,
                'status'   => (string) $status,
                'url_host' => self::host( $request->url ),
            ]
        );

        return new HttpResponse( $status, $body );
    }

    private static function host( string $url ): string {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return '';
        }

        return (string) ( $parts['host'] ?? '' );
    }
}
