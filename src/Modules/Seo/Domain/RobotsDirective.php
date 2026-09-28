<?php
/**
 * Index and follow directives. This is not a rank score.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RobotsDirective {

    public function __construct(
        private readonly bool $index,
        private readonly bool $follow,
        private readonly string $maxSnippet = '',
        private readonly string $maxImagePreview = '',
        private readonly string $maxVideoPreview = '',
    ) {
    }

    public function index(): bool {
        return $this->index;
    }

    public function follow(): bool {
        return $this->follow;
    }

    public function content(): string {
        $parts = [
            $this->index ? 'index' : 'noindex',
            $this->follow ? 'follow' : 'nofollow',
        ];
        if ( $this->maxSnippet !== '' ) {
            $parts[] = 'max-snippet:' . $this->maxSnippet;
        }
        if ( $this->maxImagePreview !== '' ) {
            $parts[] = 'max-image-preview:' . $this->maxImagePreview;
        }
        if ( $this->maxVideoPreview !== '' ) {
            $parts[] = 'max-video-preview:' . $this->maxVideoPreview;
        }

        return implode( ', ', $parts );
    }

    public static function fromStrings( string $index, string $follow ): self {
        return new self( $index !== 'noindex', $follow !== 'nofollow' );
    }
}
