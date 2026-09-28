<?php
/**
 * IndexNow queue. noindex URLs are skipped and this class does not send HTTP.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class IndexNowPlan {

    /**
     * @param list<string> $urls
     * @param list<string> $noindex
     * @return array<string, mixed>
     */
    public static function plan( array $urls, array $noindex ): array {
        $queued  = [];
        $skipped = [];
        foreach ( $urls as $url ) {
            if ( ! is_string( $url ) || preg_match( '#^https?://#', $url ) !== 1 ) {
                continue;
            }
            if ( in_array( $url, $noindex, true ) ) {
                $skipped[] = $url;
                continue;
            }
            $queued[] = $url;
        }

        return [
            'queued'    => $queued,
            'skipped'   => $skipped,
            'requested' => false,
            'note'      => 'IndexNow did not send a request. noindex URLs are skipped.',
        ];
    }
}
