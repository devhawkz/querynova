<?php
/**
 * Warns when a staging site still carries production provider identifiers.
 *
 * A stored value is a possible clone signal. It is not proof, and it is not traffic.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class StagingDataWarning {

    /**
     * @param array<string, mixed> $setup
     * @return array{environment: string, warnings: list<string>}
     */
    public function detect( string $environment, array $setup, ?string $cloudSite = null, ?string $providerProject = null ): array {
        if ( $environment !== 'staging' ) {
            return [
                'environment' => $environment,
                'warnings'    => [],
            ];
        }
        $warnings = [];
        $search   = $this->property( $setup['search_console'] ?? null );
        $ga4      = $this->property( $setup['ga4'] ?? null );
        if ( $search !== null || $ga4 !== null ) {
            $warnings[] = 'Staging has a stored Search Console or GA4 property. If this site was cloned from production, that property still points at the production property. No analytics were read.';
        }
        if ( $this->text( $cloudSite ) !== null ) {
            $warnings[] = 'Staging has a stored cloud site id. If this site was cloned from production, it may still be the production site id.';
        }
        if ( $this->text( $providerProject ) !== null ) {
            $warnings[] = 'Staging has a stored provider project. If this site was cloned from production, it may still be the production project.';
        }

        return [
            'environment' => 'staging',
            'warnings'    => $warnings,
        ];
    }

    private function property( mixed $value ): ?string {
        if ( is_string( $value ) ) {
            return $this->text( $value );
        }
        if ( ! is_array( $value ) ) {
            return null;
        }

        return $this->text( isset( $value['property'] ) && is_string( $value['property'] ) ? $value['property'] : null );
    }

    private function text( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $trimmed = trim( $value );

        return $trimmed === '' ? null : $trimmed;
    }
}
