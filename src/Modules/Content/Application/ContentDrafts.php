<?php
/**
 * Content drafts. They are stored only and never published.
 *
 * A missing model stays not connected. This class does not call a model.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentDrafts {

    public const MODEL = 'querynova_content_model';

    public const OPTION = 'querynova_content_drafts';

    /**
     * @var list<string>
     */
    public const KINDS = [
        'outline',
        'titles',
        'metas',
        'faq',
        'rewrite',
        'product_copy',
        'category_copy',
        'alt',
        'social',
        'internal_links',
    ];

    /**
     * @return array{status: string, model: string, called: bool, note: string}
     */
    public static function connection(): array {
        $model = get_option( self::MODEL, '' );
        $model = is_string( $model ) ? trim( wp_strip_all_tags( $model ) ) : '';
        if ( $model === '' ) {
            return [
                'status' => 'not_connected',
                'model'  => '',
                'called' => false,
                'note'   => 'No content model is connected. Draft actions stay unavailable until a model is connected. QueryNova did not call a model.',
            ];
        }

        return [
            'status' => 'connected',
            'model'  => $model,
            'called' => false,
            'note'   => 'A model id is stored. QueryNova does not call the model from this screen.',
        ];
    }

    /**
     * @return array{status: string, model: string, called: bool, note: string}
     */
    public static function saveModel( string $modelId ): array {
        update_option( self::MODEL, trim( wp_strip_all_tags( $modelId ) ), false );

        return self::connection();
    }

    /**
     * @return list<array{id: string, kind: string, text: string, published: bool}>
     */
    public static function recent(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $rows = [];
        foreach ( $stored as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $kind = self::kind( (string) ( $row['kind'] ?? '' ) );
            $text = trim( (string) ( $row['text'] ?? '' ) );
            if ( $kind === '' || $text === '' ) {
                continue;
            }
            $rows[] = [
                'id'        => (string) ( $row['id'] ?? '' ),
                'kind'      => $kind,
                'text'      => $text,
                'published' => false,
            ];
        }

        return array_slice( $rows, 0, 8 );
    }

    /**
     * @return array<string, mixed>
     */
    public static function store( string $kind, string $text ): array {
        $kind = self::kind( $kind );
        $text = trim( wp_strip_all_tags( $text ) );
        if ( strlen( $text ) > 4000 ) {
            $text = substr( $text, 0, 4000 );
        }
        $result = [
            'kind'         => $kind,
            'stored'       => false,
            'published'    => false,
            'page_changed' => false,
            'called'       => false,
            'note'         => 'A draft needs text. Nothing was stored and the live page was not changed.',
        ];
        if ( $kind === '' ) {
            $result['note'] = 'That draft type is not available. Nothing was stored and the live page was not changed.';

            return $result;
        }
        if ( $text === '' ) {
            return $result;
        }
        $stored = get_option( self::OPTION, [] );
        $rows   = is_array( $stored ) ? $stored : [];
        array_unshift(
            $rows,
            [
                'id'        => substr( hash( 'sha256', $kind . '|' . $text . '|' . (string) microtime( true ) ), 0, 12 ),
                'kind'      => $kind,
                'text'      => $text,
                'published' => false,
            ]
        );
        update_option( self::OPTION, array_slice( $rows, 0, 30 ), false );
        $result['stored'] = true;
        $result['note']   = 'The draft is stored. It was not published and the live page was not changed. QueryNova did not call a model.';

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function generate( string $kind, string $source ): array {
        $connection = self::connection();
        if ( $connection['status'] !== 'connected' ) {
            return [
                'kind'         => self::kind( $kind ),
                'status'       => 'not_connected',
                'stored'       => false,
                'published'    => false,
                'page_changed' => false,
                'called'       => false,
                'note'         => 'No content model is connected. This draft was not created. QueryNova did not call a model.',
            ];
        }
        $stored           = self::store( $kind, $source );
        $stored['status'] = 'not_called';
        $stored['called'] = false;
        $stored['note']   = $stored['stored'] === true
            ? 'The supplied text was stored as a draft. QueryNova did not call the model. It was not published and the live page was not changed.'
            : 'No source text was supplied, so no draft was stored. QueryNova did not call the model.';

        return $stored;
    }

    public static function kind( string $kind ): string {
        $kind = trim( $kind );

        return in_array( $kind, self::KINDS, true ) ? $kind : '';
    }
}
