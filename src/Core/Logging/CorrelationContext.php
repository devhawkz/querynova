<?php
/**
 * Request-scoped identifiers attached to logs and jobs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CorrelationContext {

    public function __construct(
        private string $correlationId,
        private string $requestId,
        private ?string $jobId = null,
    ) {
    }

    public static function fresh(): self {
        return new self( CorrelationId::generate(), CorrelationId::generate() );
    }

    public function correlationId(): string {
        return $this->correlationId;
    }

    public function requestId(): string {
        return $this->requestId;
    }

    public function jobId(): ?string {
        return $this->jobId;
    }

    public function withJob( string $jobId ): self {
        $clone        = clone $this;
        $clone->jobId = $jobId;

        return $clone;
    }

    public function setJobId( ?string $jobId ): void {
        $this->jobId = $jobId;
    }
}
