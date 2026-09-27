<?php
/**
 * One redirect rule.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectRule {

    public function __construct(
        private readonly int $id,
        private readonly string $source,
        private readonly string $target,
        private readonly int $status,
        private readonly bool $regex,
        private readonly int $hits = 0,
        private readonly bool $enabled = true,
    ) {
    }

    public function id(): int {
        return $this->id;
    }

    public function source(): string {
        return $this->source;
    }

    public function target(): string {
        return $this->target;
    }

    public function status(): int {
        return $this->status;
    }

    public function regex(): bool {
        return $this->regex;
    }

    public function hits(): int {
        return $this->hits;
    }

    public function enabled(): bool {
        return $this->enabled;
    }
}
