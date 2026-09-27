<?php
/**
 * Lab timings for one URL and strategy. There is no combined SEO score.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experience\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperienceReport {

    public function __construct(
        public readonly ?float $lcp,
        public readonly ?float $inp,
        public readonly ?float $cls,
        public readonly ?float $ttfb,
    ) {
    }
}
