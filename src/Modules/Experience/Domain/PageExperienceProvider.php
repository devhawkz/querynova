<?php
/**
 * Page experience provider. A null report means the URL was not measured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experience\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface PageExperienceProvider {

    public function id(): string;

    public function report( string $url, string $strategy ): ?ExperienceReport;
}
