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
            'redirection' => $request->redirection,
            'headers'     => array_merge(
                $request->headers,
                [
                    'X-QueryNova-Correlation' => $this->correlation->correlationId(),
                ]
            ),
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

        return new HttpResponse( $status, $body, $this->headers( $result ) );
    }

    private static function host( string $url ): string {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return '';
        }

        return (string) ( $parts['host'] ?? '' );
    }

    /**
     * @return array<string, string>
     */
    private function headers( mixed $result ): array {
        if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
            return [];
        }
        $raw  = wp_remote_retrieve_headers( $result );
        $list = [];
        if ( is_array( $raw ) ) {
            $list = $raw;
        } elseif ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
            $all  = $raw->getAll();
            $list = is_array( $all ) ? $all : [];
        }
        $headers = [];
        foreach ( $list as $name => $value ) {
            if ( ! is_string( $name ) ) {
                continue;
            }
            if ( is_array( $value ) ) {
                $value = end( $value );
            }
            if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
                $headers[ strtolower( $name ) ] = (string) $value;
            }
        }

        return $headers;
    }
}
