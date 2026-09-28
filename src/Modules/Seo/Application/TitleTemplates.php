<?php
/**
 * Title and description templates. Rendering does not write posts.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

use QueryNova\Modules\Seo\Domain\TemplateRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TitleTemplates {

    /**
     * @return list<string>
     */
    public static function contexts(): array {
        return [
            'homepage',
            'post',
            'page',
            'product',
            'product_category',
            'tag',
            'category',
            'author',
            'archive',
            'cpt',
            'taxonomy',
            'location',
        ];
    }

    /**
     * @return list<string>
     */
    public static function variables(): array {
        return [
            'title',
            'sep',
            'sitename',
            'excerpt',
            'category',
            'tag',
            'author',
            'date',
            'page',
            'pt_single',
            'pt_plural',
            'term',
            'term_description',
            'search_query',
            'location',
            'name',
        ];
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    public static function defaults(): array {
        $document = [
            'title'       => '%%title%% %%sep%% %%sitename%%',
            'description' => '%%excerpt%%',
        ];
        $term     = [
            'title'       => '%%term%% %%sep%% %%sitename%%',
            'description' => '%%term_description%%',
        ];

        return [
            'homepage'         => [
                'title'       => '%%sitename%%',
                'description' => '%%excerpt%%',
            ],
            'post'             => $document,
            'page'             => $document,
            'product'          => [
                'title'       => '%%title%% %%sep%% %%sitename%%',
                'description' => '%%excerpt%%',
            ],
            'product_category' => $term,
            'tag'              => $term,
            'category'         => $term,
            'author'           => [
                'title'       => '%%author%% %%sep%% %%sitename%%',
                'description' => '%%excerpt%%',
            ],
            'archive'          => [
                'title'       => '%%pt_plural%% %%sep%% %%sitename%%',
                'description' => '%%excerpt%%',
            ],
            'cpt'              => $document,
            'taxonomy'         => $term,
            'location'         => [
                'title'       => '%%location%% %%sep%% %%sitename%%',
                'description' => '%%excerpt%%',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, array{title: string, description: string}>
     */
    public static function merge( array $input ): array {
        $templates = self::defaults();
        foreach ( self::contexts() as $context ) {
            $row = $input[ $context ] ?? null;
            if ( ! is_array( $row ) ) {
                continue;
            }
            foreach ( [ 'title', 'description' ] as $part ) {
                if ( isset( $row[ $part ] ) && is_string( $row[ $part ] ) ) {
                    $templates[ $context ][ $part ] = trim( wp_strip_all_tags( $row[ $part ] ) );
                }
            }
        }

        return $templates;
    }

    /**
     * @param array<string, array{title: string, description: string}> $templates
     * @param array<string, string>                                    $tokens
     * @return array{title: string, description: string}
     */
    public static function render( TemplateRenderer $renderer, array $templates, string $context, array $tokens ): array {
        $row = $templates[ $context ] ?? self::defaults()['post'];

        return [
            'title'       => $renderer->render( (string) ( $row['title'] ?? '' ), $tokens ),
            'description' => $renderer->render( (string) ( $row['description'] ?? '' ), $tokens ),
        ];
    }
}
