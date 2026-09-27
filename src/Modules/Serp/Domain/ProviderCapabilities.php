<?php
/**
 * What a SERP provider says it can do. A missing capability is not a zero result.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProviderCapabilities {

    /**
     * @param list<int> $depths
     */
    public function __construct(
        public readonly bool $historicalSerp,
        public readonly array $depths,
        public readonly bool $country,
        public readonly bool $device,
        public readonly bool $location,
    ) {
    }

    public static function none(): self {
        return new self( false, [], false, false, false );
    }
}
