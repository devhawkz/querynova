<?php
/**
 * Internal event.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface EventInterface {

    public function name(): string;
}
