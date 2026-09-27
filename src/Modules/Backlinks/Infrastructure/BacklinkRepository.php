<?php
/**
 * Stored backlinks. Missing authority is written as null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Backlinks\Domain\Backlink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BacklinkRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @return list<string>
     */
    public function sourceHashes( string $targetUrl, string $provider ): array {
        $hashes = [];
        foreach ( $this->database->select(
            $this->table(),
            [
				'target_hash' => hash( 'sha256', $targetUrl ),
				'provider'    => $provider,
				'link_status' => 'active',
			],
			500
        ) as $row ) {
            $hashes[] = (string) ( $row['source_hash'] ?? '' );
        }

        return $hashes;
    }

    public function hasSnapshot( string $targetUrl, string $provider ): bool {
        return $this->sourceHashes( $targetUrl, $provider ) !== [] || $this->database->select(
            $this->table(),
            [
				'target_hash' => hash( 'sha256', $targetUrl ),
				'provider'    => $provider,
			],
			1
        ) !== [];
    }

    /**
     * @param list<Backlink> $links
     */
    public function replace( string $targetUrl, string $provider, array $links ): void {
        $now    = gmdate( 'Y-m-d H:i:s' );
        $target = hash( 'sha256', $targetUrl );
        $seen   = [];
        foreach ( $links as $link ) {
            $source          = hash( 'sha256', $link->sourceUrl );
            $seen[ $source ] = true;
            $existing        = $this->database->select(
                $this->table(),
                [
                    'target_hash' => $target,
                    'source_hash' => $source,
                    'provider'    => $provider,
                ],
                1
            );
            $data            = [
                'anchor'      => $link->anchor,
                'rel'         => $link->rel,
                'last_seen'   => $link->lastSeen ?? $now,
                'authority'   => $link->authority,
                'link_status' => 'active',
                'source'      => 'provider',
                'observed_at' => $now,
            ];
            if ( $existing === [] ) {
                $data['target_url']    = $link->targetUrl;
                $data['target_hash']   = $target;
                $data['source_url']    = $link->sourceUrl;
                $data['source_hash']   = $source;
                $data['source_domain'] = $link->sourceDomain();
                $data['first_seen']    = $link->firstSeen ?? $now;
                $data['provider']      = $provider;
                $this->database->insert( $this->table(), $data );
                continue;
            }
            $this->database->update( $this->table(), $data, [ 'id' => (int) $existing[0]['id'] ] );
        }
        foreach ( $this->database->select(
            $this->table(),
            [
				'target_hash' => $target,
				'provider'    => $provider,
			],
			500
        ) as $row ) {
            $hash = (string) ( $row['source_hash'] ?? '' );
            if ( $hash !== '' && ! isset( $seen[ $hash ] ) ) {
                $this->database->update(
                    $this->table(),
                    [
						'link_status' => 'lost',
						'observed_at' => $now,
					],
					[ 'id' => (int) $row['id'] ]
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forTarget( string $targetUrl ): array {
        return $this->database->select( $this->table(), [ 'target_hash' => hash( 'sha256', $targetUrl ) ], 200 );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent( int $limit = 20 ): array {
        $limit = max( 1, min( 20, $limit ) );

        return $this->database->select( $this->table(), [], $limit, 0, [ 'id' => 'DESC' ] );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_backlink_snapshots';
    }
}
