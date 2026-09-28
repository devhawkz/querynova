<?php
/**
 * Settings export and import for one section. Posts are not rewritten.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Reports\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsTransfer {

    /**
     * @var array<string, string>
     */
    private const SECTIONS = [
        'schedule'    => ReportSchedule::OPTION,
        'white_label' => WhiteLabel::OPTION,
        'roles'       => RoleCatalog::OPTION,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function export( string $section ): array {
        $option = self::SECTIONS[ $section ] ?? '';
        if ( $option === '' ) {
            return [
                'section' => $section,
                'payload' => null,
                'note'    => 'That settings section cannot be exported.',
            ];
        }
        $payload = get_option( $option, [] );

        return [
            'section' => $section,
            'payload' => is_array( $payload ) ? $payload : [],
            'secrets' => false,
            'note'    => 'One section was exported. Secrets are not included.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function import( string $section, array $payload, bool $confirmed ): array {
        $option = self::SECTIONS[ $section ] ?? '';
        if ( $option === '' ) {
            return [
                'section'       => $section,
                'stored'        => false,
                'changed_posts' => false,
                'note'          => 'That settings section cannot be imported.',
            ];
        }
        if ( ! $confirmed || $payload === [] ) {
            return [
                'section'       => $section,
                'stored'        => false,
                'changed_posts' => false,
                'note'          => $payload === []
                    ? 'An empty settings section is not imported. Posts were not changed.'
                    : 'Settings import stays off until you confirm. Posts were not changed.',
            ];
        }
        update_option( $option, $payload, false );

        return [
            'section'       => $section,
            'stored'        => true,
            'changed_posts' => false,
            'note'          => 'That section was stored. Posts were not changed.',
        ];
    }
}
