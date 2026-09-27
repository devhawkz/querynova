<?php
/**
 * Result of a health check.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Health;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HealthReport {

    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        private readonly string $name,
        private readonly HealthStatus $status,
        private readonly string $summary,
        private readonly array $details = [],
    ) {
    }

    public function name(): string {
        return $this->name;
    }

    public function status(): HealthStatus {
        return $this->status;
    }

    public function summary(): string {
        return $this->summary;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function details(): array {
        return $this->details;
    }

    /**
     * @return array{name: string, status: string, summary: string, details: array<string, scalar|null>}
     */
    public function toArray(): array {
        return [
            'name'    => $this->name,
            'status'  => $this->status->value,
            'summary' => $this->summary,
            'details' => $this->details,
        ];
    }
}
