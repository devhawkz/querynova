<?php
/**
 * Measured signals for one crawled URL. Missing fields stay empty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PageObservation {

    /**
     * @param list<string>                              $h1
     * @param list<string>                              $schemaTypes
     * @param list<array{lang: string, href: string}>   $hreflang
     * @param list<string>                              $internalLinks
     */
    public function __construct(
        private readonly string $url,
        private readonly int $status,
        private readonly int $depth,
        private readonly string $redirectTo,
        private readonly string $title,
        private readonly string $description,
        private readonly string $canonical,
        private readonly string $robots,
        private readonly bool $indexable,
        private readonly array $h1,
        private readonly int $wordCount,
        private readonly bool $https,
        private readonly array $schemaTypes,
        private readonly array $hreflang,
        private readonly string $nextUrl,
        private readonly string $prevUrl,
        private readonly array $internalLinks,
        private readonly string $bodyHash,
        private readonly string $error = '',
    ) {
    }

    public function url(): string {
        return $this->url;
    }

    public function status(): int {
        return $this->status;
    }

    public function depth(): int {
        return $this->depth;
    }

    public function redirectTo(): string {
        return $this->redirectTo;
    }

    public function title(): string {
        return $this->title;
    }

    public function description(): string {
        return $this->description;
    }

    public function canonical(): string {
        return $this->canonical;
    }

    public function robots(): string {
        return $this->robots;
    }

    public function indexable(): bool {
        return $this->indexable;
    }

    /**
     * @return list<string>
     */
    public function h1(): array {
        return $this->h1;
    }

    public function wordCount(): int {
        return $this->wordCount;
    }

    public function https(): bool {
        return $this->https;
    }

    /**
     * @return list<string>
     */
    public function schemaTypes(): array {
        return $this->schemaTypes;
    }

    /**
     * @return list<array{lang: string, href: string}>
     */
    public function hreflang(): array {
        return $this->hreflang;
    }

    public function nextUrl(): string {
        return $this->nextUrl;
    }

    public function prevUrl(): string {
        return $this->prevUrl;
    }

    /**
     * @return list<string>
     */
    public function internalLinks(): array {
        return $this->internalLinks;
    }

    public function bodyHash(): string {
        return $this->bodyHash;
    }

    public function error(): string {
        return $this->error;
    }

    public function withError( string $error ): self {
        return new self(
            $this->url,
            $this->status,
            $this->depth,
            $this->redirectTo,
            $this->title,
            $this->description,
            $this->canonical,
            $this->robots,
            $this->indexable,
            $this->h1,
            $this->wordCount,
            $this->https,
            $this->schemaTypes,
            $this->hreflang,
            $this->nextUrl,
            $this->prevUrl,
            $this->internalLinks,
            $this->bodyHash,
            $error
        );
    }

    public function withIndexable( bool $indexable ): self {
        return new self(
            $this->url,
            $this->status,
            $this->depth,
            $this->redirectTo,
            $this->title,
            $this->description,
            $this->canonical,
            $this->robots,
            $indexable,
            $this->h1,
            $this->wordCount,
            $this->https,
            $this->schemaTypes,
            $this->hreflang,
            $this->nextUrl,
            $this->prevUrl,
            $this->internalLinks,
            $this->bodyHash,
            $this->error
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'url'            => $this->url,
            'status'         => $this->status,
            'depth'          => $this->depth,
            'redirect_to'    => $this->redirectTo,
            'title'          => $this->title,
            'description'    => $this->description,
            'canonical'      => $this->canonical,
            'robots'         => $this->robots,
            'indexable'      => $this->indexable,
            'h1'             => $this->h1,
            'word_count'     => $this->wordCount,
            'https'          => $this->https,
            'schema_types'   => $this->schemaTypes,
            'hreflang'       => $this->hreflang,
            'next_url'       => $this->nextUrl,
            'prev_url'       => $this->prevUrl,
            'internal_links' => $this->internalLinks,
            'body_hash'      => $this->bodyHash,
            'error'          => $this->error,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray( array $row ): self {
        $h1       = is_array( $row['h1'] ?? null ) ? array_values( array_filter( $row['h1'], 'is_string' ) ) : [];
        $schema   = is_array( $row['schema_types'] ?? null ) ? array_values( array_filter( $row['schema_types'], 'is_string' ) ) : [];
        $links    = is_array( $row['internal_links'] ?? null ) ? array_values( array_filter( $row['internal_links'], 'is_string' ) ) : [];
        $hreflang = [];
        foreach ( is_array( $row['hreflang'] ?? null ) ? $row['hreflang'] : [] as $item ) {
            if ( is_array( $item ) && is_string( $item['lang'] ?? null ) && is_string( $item['href'] ?? null ) ) {
                $hreflang[] = [
                    'lang' => $item['lang'],
                    'href' => $item['href'],
                ];
            }
        }

        return new self(
            (string) ( $row['url'] ?? '' ),
            (int) ( $row['status'] ?? 0 ),
            (int) ( $row['depth'] ?? 0 ),
            (string) ( $row['redirect_to'] ?? '' ),
            (string) ( $row['title'] ?? '' ),
            (string) ( $row['description'] ?? '' ),
            (string) ( $row['canonical'] ?? '' ),
            (string) ( $row['robots'] ?? '' ),
            (bool) ( $row['indexable'] ?? false ),
            $h1,
            (int) ( $row['word_count'] ?? 0 ),
            (bool) ( $row['https'] ?? false ),
            $schema,
            $hreflang,
            (string) ( $row['next_url'] ?? '' ),
            (string) ( $row['prev_url'] ?? '' ),
            $links,
            (string) ( $row['body_hash'] ?? '' ),
            (string) ( $row['error'] ?? '' )
        );
    }
}
