<?php
/**
 * Content workspace. Observations come from a supplied document.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentWorkspace {

    /**
     * @param array<string, mixed>|null $report
     * @return array<string, mixed>
     */
    public static function present( ?array $report ): array {
        if ( $report === null ) {
            return [
                'lead'             => 'Supply a document. QueryNova does not fetch the URL.',
                'fetched'          => false,
                'intent'           => null,
                'coverage'         => null,
                'gap'              => [
                    'status' => 'UNAVAILABLE',
                    'topics' => null,
                    'note'   => 'A gap needs supplied topics and top results. QueryNova did not crawl.',
                ],
                'entities'         => null,
                'information_gain' => null,
                'evidence'         => null,
            ];
        }

        return [
            'lead'             => 'Observations from the supplied document. This is not a content score.',
            'fetched'          => false,
            'intent'           => $report['intent'] ?? null,
            'coverage'         => $report['coverage'] ?? null,
            'gap'              => $report['gap'] ?? null,
            'entities'         => $report['entities'] ?? null,
            'information_gain' => $report['information_gain'] ?? null,
            'evidence'         => $report['evidence'] ?? null,
        ];
    }
}
