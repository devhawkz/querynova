<?php
/**
 * Optional llms.txt body. It is not a ranking claim.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LlmsDocument {

    public const NOTE = 'This file is optional. It is not a proven search ranking solution.';

    /**
     * @param list<array{title: string, url: string}> $pages
     */
    public function body( array $pages, bool $enabled ): ?string {
        if ( ! $enabled ) {
            return null;
        }
        $lines = [
            '# llms.txt',
            '# ' . self::NOTE,
            '',
        ];
        foreach ( $pages as $page ) {
            $title = trim( $page['title'] );
            $url   = trim( $page['url'] );
            if ( $title === '' || $url === '' ) {
                continue;
            }
            $lines[] = '- [' . $title . '](' . $url . ')';
        }

        return implode( "\n", $lines ) . "\n";
    }
}
