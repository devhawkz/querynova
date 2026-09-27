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
    ) {
    }

    public function index(): bool {
        return $this->index;
    }

    public function follow(): bool {
        return $this->follow;
    }

    public function content(): string {
        return ( $this->index ? 'index' : 'noindex' ) . ', ' . ( $this->follow ? 'follow' : 'nofollow' );
    }

    public static function fromStrings( string $index, string $follow ): self {
        return new self( $index !== 'noindex', $follow !== 'nofollow' );
    }
}
