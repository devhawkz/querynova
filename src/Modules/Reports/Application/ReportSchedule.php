<?php
/**
 * Stored report schedule. No email is sent and customer records are not included.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Reports\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ReportSchedule {

    public const OPTION = 'querynova_report_schedule';

    /**
     * @param list<string> $recipients
     * @return array<string, mixed>
     */
    public static function plan( array $recipients, string $kind, string $frequency, bool $confirmed ): array {
        $emails = self::emails( $recipients );
        $result = [
            'recipients' => $emails,
            'kind'       => trim( $kind ),
            'frequency'  => trim( $frequency ),
            'stored'     => false,
            'sent'       => false,
            'customers'  => false,
            'note'       => 'The schedule is not stored until you confirm. No email was sent.',
        ];
        if ( ! $confirmed || $emails === [] || $result['kind'] === '' ) {
            return $result;
        }
        update_option(
            self::OPTION,
            [
                'recipients' => $emails,
                'kind'       => $result['kind'],
                'frequency'  => $result['frequency'],
                'customers'  => false,
            ],
            false
        );
        $result['stored'] = true;
        $result['note']   = 'The schedule is stored. No email was sent. The report uses aggregate metrics and does not include customer records.';

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return [
                'recipients' => [],
                'kind'       => '',
                'frequency'  => '',
                'sent'       => false,
                'customers'  => false,
            ];
        }

        return [
            'recipients' => self::emails( is_array( $stored['recipients'] ?? null ) ? $stored['recipients'] : [] ),
            'kind'       => is_string( $stored['kind'] ?? null ) ? $stored['kind'] : '',
            'frequency'  => is_string( $stored['frequency'] ?? null ) ? $stored['frequency'] : '',
            'sent'       => false,
            'customers'  => false,
        ];
    }

    /**
     * @param list<mixed> $recipients
     * @return list<string>
     */
    private static function emails( array $recipients ): array {
        $emails = [];
        foreach ( $recipients as $recipient ) {
            if ( ! is_string( $recipient ) ) {
                continue;
            }
            $email = self::email( $recipient );
            if ( $email === '' || in_array( $email, $emails, true ) ) {
                continue;
            }
            $emails[] = $email;
            if ( count( $emails ) >= 10 ) {
                break;
            }
        }

        return $emails;
    }

    private static function email( string $value ): string {
        $value = strtolower( trim( wp_strip_all_tags( $value ) ) );
        if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) === false ) {
            return '';
        }

        return $value;
    }
}
