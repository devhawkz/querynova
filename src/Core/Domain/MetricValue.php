<?php
/**
 * A metric that always carries provenance. Missing data is unavailable, never zero.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MetricValue {

    public function __construct(
        private readonly ?float $value,
        private readonly ProvenanceKind $kind,
        private readonly DataSource $source,
        private readonly ?string $provider,
        private readonly ?string $methodology,
        private readonly ?string $methodologyVersion,
        private readonly ConfidenceBand $confidence,
        private readonly \DateTimeImmutable $observedAt,
        private readonly string $reason = '',
    ) {
        if ( $kind === ProvenanceKind::Unavailable && $value !== null ) {
            throw new \InvalidArgumentException( 'Unavailable metrics cannot carry a numeric value.' );
        }
        if ( $kind !== ProvenanceKind::Unavailable && $value === null ) {
            throw new \InvalidArgumentException( 'A present metric kind requires a value.' );
        }
    }

    public static function unavailable( DataSource $source, string $reason, \DateTimeImmutable $observedAt ): self {
        return new self( null, ProvenanceKind::Unavailable, $source, null, null, null, ConfidenceBand::Unknown, $observedAt, $reason );
    }

    public function value(): ?float {
        return $this->value;
    }

    public function kind(): ProvenanceKind {
        return $this->kind;
    }

    public function source(): DataSource {
        return $this->source;
    }

    public function provider(): ?string {
        return $this->provider;
    }

    public function methodology(): ?string {
        return $this->methodology;
    }

    public function methodologyVersion(): ?string {
        return $this->methodologyVersion;
    }

    public function confidence(): ConfidenceBand {
        return $this->confidence;
    }

    public function observedAt(): \DateTimeImmutable {
        return $this->observedAt;
    }

    public function reason(): string {
        return $this->reason;
    }

    public function isAvailable(): bool {
        return $this->kind !== ProvenanceKind::Unavailable;
    }

    public function isMeasured(): bool {
        return $this->kind === ProvenanceKind::Measured;
    }

    /**
     * Display label. Estimated values are never labeled as measured.
     */
    public function displayLabel(): string {
        return match ( $this->kind ) {
            ProvenanceKind::Measured => 'Measured',
            ProvenanceKind::Attributed => 'Attributed',
            ProvenanceKind::Estimated => 'Estimated',
            ProvenanceKind::Unavailable => 'Unavailable',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'value'               => $this->value,
            'kind'                => $this->kind->value,
            'label'               => $this->displayLabel(),
            'source'              => $this->source->value,
            'provider'            => $this->provider,
            'methodology'         => $this->methodology,
            'methodology_version' => $this->methodologyVersion,
            'confidence'          => $this->confidence->value,
            'observed_at'         => $this->observedAt->format( 'c' ),
            'reason'              => $this->reason,
        ];
    }
}
