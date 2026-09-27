<?php
/**
 * Compares the top 10 URLs of two stored snapshots.
 *
 * Overlap of 80 percent or more is stable, 50 percent or more is moderately volatile,
 * and less than that is highly volatile. One snapshot is not labeled stable.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpStability {

    /**
     * @param list<string> $previousUrls
     * @param list<string> $currentUrls
     */
    public function compare( array $previousUrls, array $currentUrls ): ?string {
        $previous = $this->top( $previousUrls );
        $current  = $this->top( $currentUrls );
        if ( $previous === [] || $current === [] ) {
            return null;
        }
        $shared = 0;
        foreach ( $current as $url ) {
            if ( in_array( $url, $previous, true ) ) {
                ++$shared;
            }
        }
        $overlap = $shared / count( $previous );
        if ( $overlap >= 0.8 ) {
            return 'stable';
        }
        if ( $overlap >= 0.5 ) {
            return 'moderately_volatile';
        }

        return 'highly_volatile';
    }

    /**
     * @param list<string> $urls
     * @return list<string>
     */
    private function top( array $urls ): array {
        $rows = [];
        foreach ( $urls as $url ) {
            if ( $url === '' ) {
                continue;
            }
            $rows[] = $url;
            if ( count( $rows ) >= 10 ) {
                break;
            }
        }

        return $rows;
    }
}
