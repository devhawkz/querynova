<?php
/**
 * One keyword. Volume, CPC, rank, and difficulty stay null until they are supplied.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Keywords\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class KeywordRecord {

    /**
     * @param list<float>|null $trend
     */
    public function __construct(
        public readonly int $id,
        public readonly string $keyword,
        public readonly string $country,
        public readonly string $language,
        public readonly ?int $volume,
        public readonly ?string $cpc,
        public readonly ?float $paidCompetition,
        public readonly ?int $organicDifficulty,
        public readonly ?array $trend,
        public readonly ?int $rank,
        public readonly string $rankingUrl,
        public readonly string $source,
        public readonly string $provider,
        public readonly ?string $methodology,
    ) {
    }

    public function hash(): string {
        return hash( 'sha256', strtolower( trim( $this->keyword ) ) . '|' . $this->country . '|' . $this->language );
    }
}
