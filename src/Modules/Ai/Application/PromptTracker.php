<?php
/**
 * Stored prompt tracking. Saving a prompt does not call a model.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PromptTracker {

    public const OPTION = 'querynova_ai_prompts';

    /**
     * @param list<string> $tags
     * @return array<string, mixed>
     */
    public static function track( string $prompt, string $locale, string $country, string $language, string $provider, string $frequency, array $tags ): array {
        $prompt = trim( wp_strip_all_tags( $prompt ) );
        $result = [
            'stored'    => false,
            'called'    => false,
            'connected' => false,
            'note'      => 'A prompt is required. Nothing was stored and QueryNova did not call a model.',
        ];
        if ( $prompt === '' ) {
            return $result;
        }
        if ( strlen( $prompt ) > 500 ) {
            $prompt = substr( $prompt, 0, 500 );
        }
        $row    = [
            'prompt'    => $prompt,
            'locale'    => self::plain( $locale, 32 ),
            'country'   => self::plain( $country, 32 ),
            'language'  => self::plain( $language, 32 ),
            'provider'  => self::plain( $provider, 64 ),
            'frequency' => self::plain( $frequency, 32 ),
            'tags'      => self::tags( $tags ),
        ];
        $stored = get_option( self::OPTION, [] );
        $rows   = is_array( $stored ) ? $stored : [];
        array_unshift( $rows, $row );
        update_option( self::OPTION, array_slice( $rows, 0, 100 ), false );
        $result['stored'] = true;
        $result['prompt'] = $row;
        $result['note']   = 'The prompt is stored. QueryNova did not call a model. Storing a provider name does not connect one.';

        return $result;
    }

    /**
     * @return list<array{prompt: string, locale: string, country: string, language: string, provider: string, frequency: string, tags: list<string>}>
     */
    public static function catalog(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $rows = [];
        foreach ( $stored as $row ) {
            if ( ! is_array( $row ) || trim( (string) ( $row['prompt'] ?? '' ) ) === '' ) {
                continue;
            }
            $rows[] = [
                'prompt'    => (string) $row['prompt'],
                'locale'    => (string) ( $row['locale'] ?? '' ),
                'country'   => (string) ( $row['country'] ?? '' ),
                'language'  => (string) ( $row['language'] ?? '' ),
                'provider'  => (string) ( $row['provider'] ?? '' ),
                'frequency' => (string) ( $row['frequency'] ?? '' ),
                'tags'      => self::tags( is_array( $row['tags'] ?? null ) ? $row['tags'] : [] ),
            ];
        }

        return array_slice( $rows, 0, 100 );
    }

    /**
     * @param list<mixed> $tags
     * @return list<string>
     */
    private static function tags( array $tags ): array {
        $clean = [];
        foreach ( $tags as $tag ) {
            if ( ! is_string( $tag ) ) {
                continue;
            }
            $tag = self::plain( $tag, 40 );
            if ( $tag === '' || in_array( $tag, $clean, true ) ) {
                continue;
            }
            $clean[] = $tag;
            if ( count( $clean ) >= 12 ) {
                break;
            }
        }

        return $clean;
    }

    private static function plain( string $value, int $limit ): string {
        $value = trim( wp_strip_all_tags( $value ) );
        if ( strlen( $value ) > $limit ) {
            $value = substr( $value, 0, $limit );
        }

        return $value;
    }
}
