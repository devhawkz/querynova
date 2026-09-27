<?php
/**
 * Stored prompts and observations. A missing provider does not insert a zero mention.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Ai\Domain\AiObservation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AiRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    public function savePrompt( string $prompt, string $locale, string $provider, string $model ): int {
        $now = gmdate( 'Y-m-d H:i:s' );

        return $this->database->insert(
            $this->table( 'ai_prompts' ),
            [
                'prompt'     => $prompt,
                'locale'     => $locale,
                'provider'   => $provider,
                'model'      => $model,
                'active'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function saveRun( int $promptId, string $provider, string $model, AiObservation $observation ): int {
        $now   = gmdate( 'Y-m-d H:i:s' );
        $runId = $this->database->insert(
            $this->table( 'ai_runs' ),
            [
                'prompt_id'        => $promptId,
                'provider'         => $provider,
                'model'            => $model,
                'ran_at'           => $now,
                'status'           => 'complete',
                'response_excerpt' => substr( $observation->excerpt, 0, 500 ),
                'brand_mentioned'  => $observation->brandMentioned ? 1 : 0,
                'correlation_id'   => '',
                'error_reference'  => '',
            ]
        );
        if ( $observation->brandMentioned ) {
            $this->mention( $runId, 'brand', '', null, '', false, $now );
        }
        if ( $observation->productMentioned ) {
            $this->mention( $runId, 'product', '', null, '', false, $now );
        }
        if ( $observation->domainCited && $observation->citedUrl !== null && $observation->citedUrl !== '' ) {
            $host = wp_parse_url( $observation->citedUrl, PHP_URL_HOST );
            $this->mention( $runId, 'citation', '', $observation->citedUrl, is_string( $host ) ? $host : '', true, $now );
        }
        foreach ( $observation->competitorsMentioned as $name ) {
            $this->mention( $runId, 'competitor', $name, null, '', false, $now );
        }

        return $runId;
    }

    public function hasRuns(): bool {
        return $this->database->select( $this->table( 'ai_runs' ), [], 1 ) !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function runs(): array {
        return $this->database->select( $this->table( 'ai_runs' ), [], 100, 0, [ 'id' => 'DESC' ] );
    }

    private function mention( int $runId, string $type, string $name, ?string $url, string $domain, bool $cited, string $now ): void {
        $this->database->insert(
            $this->table( 'ai_mentions' ),
            [
                'run_id'       => $runId,
                'mention_type' => $type,
                'name'         => $name,
                'url'          => $url,
                'domain'       => $domain,
                'cited'        => $cited ? 1 : 0,
                'created_at'   => $now,
            ]
        );
    }

    private function table( string $name ): string {
        return $this->database->prefix() . 'qn_' . $name;
    }
}
