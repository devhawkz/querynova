<?php
/**
 * A URL considered for a sitemap before inclusion rules run.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapCandidate {

    /**
     * @param list<string>                  $images
     * @param list<array{url: string, title: string, thumbnail: string}> $videos
     */
    public function __construct(
        private readonly string $url,
        private readonly string $channel,
        private readonly string $status,
        private readonly string $visibility,
        private readonly bool $passwordProtected,
        private readonly bool $indexable,
        private readonly string $canonical,
        private readonly string $lastModified,
        private readonly array $images = [],
        private readonly array $videos = [],
        private readonly string $newsPublishedAt = '',
        private readonly string $newsTitle = '',
    ) {
    }

    public function url(): string {
        return $this->url;
    }

    public function channel(): string {
        return $this->channel;
    }

    public function status(): string {
        return $this->status;
    }

    public function visibility(): string {
        return $this->visibility;
    }

    public function passwordProtected(): bool {
        return $this->passwordProtected;
    }

    public function indexable(): bool {
        return $this->indexable;
    }

    public function canonical(): string {
        return $this->canonical;
    }

    public function lastModified(): string {
        return $this->lastModified;
    }

    /**
     * @return list<string>
     */
    public function images(): array {
        return $this->images;
    }

    /**
     * @return list<array{url: string, title: string, thumbnail: string}>
     */
    public function videos(): array {
        return $this->videos;
    }

    public function newsPublishedAt(): string {
        return $this->newsPublishedAt;
    }

    public function newsTitle(): string {
        return $this->newsTitle;
    }
}
