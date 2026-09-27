<?php
/**
 * Stored lab timings. Omitted metrics are written as null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experience\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Experience\Domain\ExperienceReport;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperienceRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    public function save( string $url, string $strategy, string $provider, ExperienceReport $report ): void {
        $now      = gmdate( 'Y-m-d H:i:s' );
        $hash     = hash( 'sha256', $url );
        $where    = [
            'url_hash' => $hash,
            'strategy' => $strategy,
        ];
        $data     = array_merge(
            $where,
            [
                'url'         => $url,
                'lcp'         => $report->lcp,
                'inp'         => $report->inp,
                'cls'         => $report->cls,
                'ttfb'        => $report->ttfb,
                'source'      => $provider === '' ? 'unavailable' : $provider,
                'observed_at' => $now,
            ]
        );
        $existing = $this->database->select( $this->table(), $where, 1 );
        if ( $existing === [] ) {
            $this->database->insert( $this->table(), $data );
            return;
        }
        $this->database->update( $this->table(), $data, $where );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest( string $url, string $strategy ): ?array {
        $rows = $this->database->select(
            $this->table(),
            [
                'url_hash' => hash( 'sha256', $url ),
                'strategy' => $strategy,
            ],
            1
        );

        return $rows[0] ?? null;
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_page_experience';
    }
}
