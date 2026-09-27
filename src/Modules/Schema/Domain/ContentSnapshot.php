<?php
/**
 * Page facts the schema graph can use. It is not a WordPress object.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentSnapshot {

    /**
     * @param array<string, string>                  $custom
     * @param list<array{name: string, url: string}> $breadcrumbs
     */
    public function __construct(
        private readonly string $permalink = '',
        private readonly string $title = '',
        private readonly string $description = '',
        private readonly string $siteName = '',
        private readonly string $siteUrl = '',
        private readonly string $authorName = '',
        private readonly string $publishedAt = '',
        private readonly string $modifiedAt = '',
        private readonly string $image = '',
        private readonly string $kind = 'post',
        private readonly array $custom = [],
        private readonly array $breadcrumbs = [],
        private readonly ?CommerceFacts $commerce = null,
        private readonly string $videoUrl = '',
        private readonly string $videoTitle = '',
        private readonly string $videoThumbnail = '',
    ) {
    }

    public function permalink(): string {
        return $this->permalink;
    }

    public function title(): string {
        return $this->title;
    }

    public function description(): string {
        return $this->description;
    }

    public function siteName(): string {
        return $this->siteName;
    }

    public function siteUrl(): string {
        return $this->siteUrl;
    }

    public function authorName(): string {
        return $this->authorName;
    }

    public function publishedAt(): string {
        return $this->publishedAt;
    }

    public function modifiedAt(): string {
        return $this->modifiedAt;
    }

    public function image(): string {
        return $this->image;
    }

    public function kind(): string {
        return $this->kind;
    }

    /**
     * @return array<string, string>
     */
    public function custom(): array {
        return $this->custom;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    public function breadcrumbs(): array {
        return $this->breadcrumbs;
    }

    public function commerce(): ?CommerceFacts {
        return $this->commerce;
    }

    public function videoUrl(): string {
        return $this->videoUrl;
    }

    public function videoTitle(): string {
        return $this->videoTitle;
    }

    public function videoThumbnail(): string {
        return $this->videoThumbnail;
    }
}
