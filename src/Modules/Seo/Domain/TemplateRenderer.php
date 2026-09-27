<?php
/**
 * Renders SEO templates. Unknown tokens are removed rather than printed.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TemplateRenderer {

    /**
     * @param array<string, string> $tokens
     */
    public function render( string $template, array $tokens ): string {
        $output = $template;
        foreach ( $tokens as $token => $value ) {
            $output = str_replace( '%%' . $token . '%%', $value, $output );
        }
        $output = preg_replace( '/%%[a-z0-9_]+%%/i', '', $output ) ?? $output;
        $output = preg_replace( '/\s+/', ' ', $output ) ?? $output;

        return trim( $output, " \t\n\r\0\x0B-|" );
    }
}
