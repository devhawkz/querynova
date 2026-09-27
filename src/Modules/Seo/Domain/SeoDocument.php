<?php
/**
 * Resolved head tags for one URL.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoDocument {

    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly string $canonical,
        private readonly RobotsDirective $robots,
        private readonly string $openGraphTitle,
        private readonly string $openGraphDescription,
        private readonly string $openGraphImage,
        private readonly string $twitterCard,
    ) {
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

    public function robots(): RobotsDirective {
        return $this->robots;
    }

    public function openGraphTitle(): string {
        return $this->openGraphTitle;
    }

    public function openGraphDescription(): string {
        return $this->openGraphDescription;
    }

    public function openGraphImage(): string {
        return $this->openGraphImage;
    }

    public function twitterCard(): string {
        return $this->twitterCard;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array {
        return [
            'title'                  => $this->title,
            'description'            => $this->description,
            'canonical'              => $this->canonical,
            'robots'                 => $this->robots->content(),
            'open_graph_title'       => $this->openGraphTitle,
            'open_graph_description' => $this->openGraphDescription,
            'open_graph_image'       => $this->openGraphImage,
            'twitter_card'           => $this->twitterCard,
        ];
    }
}
