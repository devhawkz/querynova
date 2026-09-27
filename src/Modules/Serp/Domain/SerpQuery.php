<?php
/**
 * One SERP request. Depth is only the supported result counts.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Domain;

use QueryNova\Core\Exceptions\ValidationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpQuery {

    /**
     * @param list<int> $depths
     */
    public const DEPTHS = [ 10, 20, 100 ];

    public function __construct(
        public readonly int $keywordId,
        public readonly string $keyword,
        public readonly string $location,
        public readonly string $country,
        public readonly string $language,
        public readonly string $device,
        public readonly int $depth,
        public readonly string $domain,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray( array $input ): self {
        $keyword = trim( (string) ( $input['keyword'] ?? '' ) );
        $depth   = (int) ( $input['depth'] ?? 10 );
        $device  = strtolower( trim( (string) ( $input['device'] ?? 'desktop' ) ) );
        if ( $keyword === '' ) {
            throw new ValidationException( 'A keyword is required.' );
        }
        if ( ! in_array( $depth, self::DEPTHS, true ) ) {
            throw new ValidationException( 'SERP depth must be 10, 20, or 100.' );
        }
        if ( ! in_array( $device, [ 'desktop', 'mobile', 'tablet' ], true ) ) {
            throw new ValidationException( 'Device must be desktop, mobile, or tablet.' );
        }

        return new self(
            max( 0, (int) ( $input['keyword_id'] ?? 0 ) ),
            $keyword,
            trim( (string) ( $input['location'] ?? '' ) ),
            strtolower( trim( (string) ( $input['country'] ?? '' ) ) ),
            strtolower( trim( (string) ( $input['language'] ?? '' ) ) ),
            $device,
            $depth,
            strtolower( trim( (string) ( $input['domain'] ?? '' ) ) )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'keyword_id' => $this->keywordId,
            'keyword'    => $this->keyword,
            'location'   => $this->location,
            'country'    => $this->country,
            'language'   => $this->language,
            'device'     => $this->device,
            'depth'      => $this->depth,
            'domain'     => $this->domain,
        ];
    }
}
