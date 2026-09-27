<?php
/**
 * One crawler finding. It is not a ranking score.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlIssue {

    public function __construct(
        private readonly string $code,
        private readonly string $severity,
        private readonly string $url,
        private readonly string $title,
        private readonly string $detail,
    ) {
    }

    public function code(): string {
        return $this->code;
    }

    public function severity(): string {
        return $this->severity;
    }

    public function url(): string {
        return $this->url;
    }

    public function title(): string {
        return $this->title;
    }

    public function detail(): string {
        return $this->detail;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array {
        return [
            'code'     => $this->code,
            'severity' => $this->severity,
            'url'      => $this->url,
            'title'    => $this->title,
            'detail'   => $this->detail,
        ];
    }
}
