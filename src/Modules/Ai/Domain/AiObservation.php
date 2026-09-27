<?php
/**
 * One model observation. There is no rank position.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AiObservation {

    /**
     * @param list<string> $competitorsMentioned
     * @param list<string> $competitorCitations
     */
    public function __construct(
        public readonly bool $brandMentioned,
        public readonly bool $productMentioned,
        public readonly bool $domainCited,
        public readonly ?string $citedUrl,
        public readonly array $competitorsMentioned,
        public readonly array $competitorCitations,
        public readonly string $excerpt,
    ) {
    }
}
