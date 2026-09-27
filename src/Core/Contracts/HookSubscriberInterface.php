<?php
/**
 * WordPress hook subscriber.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface HookSubscriberInterface {

    /**
     * Map of hook name to subscriber method, or [method, priority, accepted args].
     *
     * @return array<string, string|array{0:string,1:int,2:int}>
     */
    public function hooks(): array;

    public function hookType( string $hook ): string;
}
