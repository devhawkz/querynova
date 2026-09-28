<?php
/**
 * Stored SEO fields for one document. Reading does not write meta.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EditorDocument {

    /**
     * @return array{
     *   stored: array<string, string>,
     *   preview: array{title: string, description: string, url: string},
     *   dangerous: array{robots_index: bool, canonical: bool}
     * }
     */
    public static function view( SeoMetaService $seo, string $objectType, int $objectId, string $fallbackTitle, string $permalink ): array {
        $stored = [];
        foreach ( SeoMetaService::keys() as $key ) {
            $stored[ $key ] = $seo->stored( $objectType, $objectId, $key );
        }
        $title = $stored[ SeoMetaService::TITLE ] !== '' ? $stored[ SeoMetaService::TITLE ] : $fallbackTitle;

        return [
            'stored'    => $stored,
            'preview'   => [
                'title'       => $title,
                'description' => $stored[ SeoMetaService::DESCRIPTION ],
                'url'         => $permalink,
            ],
            'dangerous' => [
                'robots_index' => $stored[ SeoMetaService::ROBOTS_INDEX ] === 'noindex',
                'canonical'    => $stored[ SeoMetaService::CANONICAL ] !== '',
            ],
        ];
    }
}
