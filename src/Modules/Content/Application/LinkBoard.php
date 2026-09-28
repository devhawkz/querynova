<?php
/**
 * Link graph presentation. Suggestions stay Suggest Only.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LinkBoard {

    /**
     * @param list<array{url: string, links: list<string>, depth?: int|null, anchors?: list<string>, broken?: list<string>}> $pages
     * @param list<array{type: string, name: string, url: string}> $suggestions
     * @return array<string, mixed>
     */
    public static function present( array $pages, array $suggestions = [] ): array {
        $graph = ( new ContentIntelligence() )->linkGraph( $pages, '' );
        $links = ( new ContentIntelligence() )->commerceLinks( $suggestions );
        $rows  = [];
        foreach ( $links as $link ) {
            $rows[] = [
                'from'    => $link['from'],
                'to'      => $link['to'],
                'reason'  => $link['reason'],
                'applied' => false,
                'mode'    => 'Suggest Only',
            ];
        }

        return [
            'mode'        => 'Suggest Only',
            'inserted'    => false,
            'broken'      => $graph['broken'],
            'orphans'     => $graph['orphans'],
            'suggestions' => $rows,
            'note'        => 'Suggestions stay Suggest Only. Nothing is inserted.',
        ];
    }
}
