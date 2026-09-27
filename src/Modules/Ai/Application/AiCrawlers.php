<?php
/**
 * Known AI crawlers and a robots.txt reading. Rules are not changed.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AiCrawlers {

    /**
     * @return list<array{provider: string, crawler: string, user_agent: string, purpose: string, documentation: string, last_verified: null, access: bool|null}>
     */
    public function registry( string $robots ): array {
        $rows = [];
        foreach ( $this->catalog() as $crawler ) {
            $crawler['last_verified'] = null;
            $crawler['access']        = $this->access( $robots, $crawler['user_agent'] );
            $rows[]                   = $crawler;
        }

        return $rows;
    }

    public function access( string $robots, string $agent ): ?bool {
        if ( trim( $robots ) === '' ) {
            return null;
        }
        $groups = $this->groups( $robots );
        $rules  = $groups[ strtolower( $agent ) ] ?? $groups['*'] ?? null;
        if ( $rules === null ) {
            return null;
        }
        foreach ( $rules as $disallow ) {
            if ( $disallow === '/' ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{provider: string, crawler: string, user_agent: string, purpose: string, documentation: string}>
     */
    private function catalog(): array {
        return [
            [
                'provider'      => 'OpenAI',
                'crawler'       => 'GPTBot',
                'user_agent'    => 'GPTBot',
                'purpose'       => 'training',
                'documentation' => 'https://platform.openai.com/docs/bots',
            ],
            [
                'provider'      => 'OpenAI',
                'crawler'       => 'OAI-SearchBot',
                'user_agent'    => 'OAI-SearchBot',
                'purpose'       => 'search',
                'documentation' => 'https://platform.openai.com/docs/bots',
            ],
            [
                'provider'      => 'Google',
                'crawler'       => 'Google-Extended',
                'user_agent'    => 'Google-Extended',
                'purpose'       => 'training',
                'documentation' => 'https://developers.google.com/search/docs/crawling-indexing/google-extended',
            ],
            [
                'provider'      => 'Anthropic',
                'crawler'       => 'ClaudeBot',
                'user_agent'    => 'ClaudeBot',
                'purpose'       => 'training',
                'documentation' => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawl',
            ],
            [
                'provider'      => 'Perplexity',
                'crawler'       => 'PerplexityBot',
                'user_agent'    => 'PerplexityBot',
                'purpose'       => 'search',
                'documentation' => 'https://docs.perplexity.ai/guides/bots',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function groups( string $robots ): array {
        $groups = [];
        $agents = [];
        $rules  = [];
        $lines  = preg_split( '/\r\n|\n|\r/', $robots );
        if ( ! is_array( $lines ) ) {
            return [];
        }
        foreach ( $lines as $line ) {
            $clean = trim( (string) preg_replace( '/#.*$/', '', $line ) );
            if ( $clean === '' ) {
                $this->flush( $groups, $agents, $rules );
                continue;
            }
            if ( ! str_contains( $clean, ':' ) ) {
                continue;
            }
            [ $key, $value ] = array_map( 'trim', explode( ':', $clean, 2 ) );
            $key             = strtolower( $key );
            if ( $key === 'user-agent' ) {
                if ( $rules !== [] ) {
                    $this->flush( $groups, $agents, $rules );
                }
                $agents[] = strtolower( $value );
                continue;
            }
            if ( $key === 'disallow' ) {
                $rules[] = $value;
            }
        }
        $this->flush( $groups, $agents, $rules );

        return $groups;
    }

    /**
     * @param array<string, list<string>> $groups
     * @param list<string>                $agents
     * @param list<string>                $rules
     */
    private function flush( array &$groups, array &$agents, array &$rules ): void {
        foreach ( $agents as $agent ) {
            $groups[ $agent ] = $rules;
        }
        $agents = [];
        $rules  = [];
    }
}
