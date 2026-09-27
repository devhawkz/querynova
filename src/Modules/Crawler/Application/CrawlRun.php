<?php
/**
 * Serializable state for one crawl. A job carries this between batches.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

use QueryNova\Modules\Crawler\Domain\PageObservation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlRun {

    /**
     * @param list<string>                         $sitemap
     * @param list<array{url: string, depth: int}> $queue
     * @param list<string>                         $seen
     * @param list<PageObservation>                $pages
     * @param list<string>|null                    $disallow Null until robots.txt has been read.
     */
    public function __construct(
        private readonly string $runId,
        private readonly string $origin,
        private readonly array $sitemap,
        private readonly int $maxPages,
        private readonly array $queue,
        private readonly array $seen,
        private readonly array $pages,
        private readonly ?array $disallow,
        private readonly bool $done,
    ) {
    }

    public function runId(): string {
        return $this->runId;
    }

    public function origin(): string {
        return $this->origin;
    }

    /**
     * @return list<string>
     */
    public function sitemap(): array {
        return $this->sitemap;
    }

    public function maxPages(): int {
        return $this->maxPages;
    }

    /**
     * @return list<array{url: string, depth: int}>
     */
    public function queue(): array {
        return $this->queue;
    }

    /**
     * @return list<string>
     */
    public function seen(): array {
        return $this->seen;
    }

    /**
     * @return list<PageObservation>
     */
    public function pages(): array {
        return $this->pages;
    }

    /**
     * @return list<string>|null
     */
    public function disallow(): ?array {
        return $this->disallow;
    }

    public function done(): bool {
        return $this->done;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array {
        $pages = [];
        foreach ( $this->pages as $page ) {
            $pages[] = $page->toArray();
        }

        return [
            'run_id'    => $this->runId,
            'origin'    => $this->origin,
            'sitemap'   => $this->sitemap,
            'max_pages' => $this->maxPages,
            'queue'     => $this->queue,
            'seen'      => $this->seen,
            'pages'     => $pages,
            'disallow'  => $this->disallow,
            'done'      => $this->done,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload( array $payload ): self {
        $sitemap = self::strings( $payload['sitemap'] ?? null );
        $seen    = self::strings( $payload['seen'] ?? null );
        $queue   = [];
        foreach ( is_array( $payload['queue'] ?? null ) ? $payload['queue'] : [] as $item ) {
            if ( is_array( $item ) && is_string( $item['url'] ?? null ) ) {
                $queue[] = [
                    'url'   => $item['url'],
                    'depth' => (int) ( $item['depth'] ?? 0 ),
                ];
            }
        }
        $pages = [];
        foreach ( is_array( $payload['pages'] ?? null ) ? $payload['pages'] : [] as $row ) {
            if ( is_array( $row ) ) {
                $pages[] = PageObservation::fromArray( $row );
            }
        }
        $disallow = null;
        if ( is_array( $payload['disallow'] ?? null ) ) {
            $disallow = self::strings( $payload['disallow'] );
        }

        return new self(
            (string) ( $payload['run_id'] ?? '' ),
            (string) ( $payload['origin'] ?? '' ),
            $sitemap,
            (int) ( $payload['max_pages'] ?? CrawlBatch::MAX_PAGES ),
            $queue,
            $seen,
            $pages,
            $disallow,
            (bool) ( $payload['done'] ?? false )
        );
    }

    /**
     * @return list<string>
     */
    private static function strings( mixed $values ): array {
        if ( ! is_array( $values ) ) {
            return [];
        }
        $strings = [];
        foreach ( $values as $value ) {
            if ( is_string( $value ) ) {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
