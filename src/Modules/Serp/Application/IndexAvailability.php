<?php
/**
 * Index status and trends. Rows appear only when a provider supplied them.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class IndexAvailability {

    /**
     * @param list<array<string, mixed>>|null $rows
     * @return array<string, mixed>
     */
    public static function report( string $providerId, ?array $rows, string $name ): array {
        if ( $providerId === '' || $rows === null ) {
            return [
                'name'   => $name,
                'status' => 'not_available',
                'state'  => 'Not available',
                'rows'   => null,
                'note'   => $name . ' is shown only when a provider supplies it.',
            ];
        }

        return [
            'name'   => $name,
            'status' => 'supplied',
            'state'  => 'Supplied',
            'rows'   => $rows,
            'note'   => 'These rows came from the provider. They are not a ranking score. No chart is drawn.',
        ];
    }
}
