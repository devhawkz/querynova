<?php
/**
 * 404 monitor storage. Recording a miss never creates a redirect.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NotFoundRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    public function record( string $url, string $referrer, string $userAgent, string $suggestedTarget ): void {
        $now   = gmdate( 'Y-m-d H:i:s' );
        $hash  = hash( 'sha256', $url );
        $agent = substr( wp_strip_all_tags( $userAgent ), 0, 180 );
        $rows  = $this->database->select( $this->table(), [ 'url_hash' => $hash ], 1 );
        if ( $rows === [] ) {
            $this->database->insert(
                $this->table(),
                [
                    'url'              => $url,
                    'url_hash'         => $hash,
                    'hits'             => 1,
                    'referrer'         => $referrer,
                    'user_agent'       => $agent,
                    'user_agent_hash'  => $agent === '' ? '' : hash( 'sha256', $agent ),
                    'first_seen'       => $now,
                    'last_seen'        => $now,
                    'suggested_target' => $suggestedTarget,
                ]
            );

            return;
        }
        $hits = (int) ( $rows[0]['hits'] ?? 0 ) + 1;
        $data = [
            'hits'             => $hits,
            'last_seen'        => $now,
            'suggested_target' => $suggestedTarget,
        ];
        if ( $referrer !== '' ) {
            $data['referrer'] = $referrer;
        }
        $this->database->update( $this->table(), $data, [ 'url_hash' => $hash ] );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(): array {
        return $this->database->select( $this->table(), [], 100, 0, [ 'hits' => 'DESC' ] );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_not_found';
    }
}
