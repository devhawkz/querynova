<?php
/**
 * The result of resolving a request path against redirect rules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectDecision {

    public function __construct(
        private readonly bool $matched,
        private readonly int $status,
        private readonly string $location,
        private readonly int $ruleId,
        private readonly bool $loop,
    ) {
    }

    public function matched(): bool {
        return $this->matched;
    }

    public function status(): int {
        return $this->status;
    }

    public function location(): string {
        return $this->location;
    }

    public function ruleId(): int {
        return $this->ruleId;
    }

    public function loop(): bool {
        return $this->loop;
    }
}
