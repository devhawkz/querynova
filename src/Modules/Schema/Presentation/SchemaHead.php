<?php
/**
 * Prints the JSON-LD graph. It does not call providers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Presentation;

use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Modules\Schema\Application\SchemaDocumentBuilder;
use QueryNova\Modules\Schema\Domain\SchemaRuleStore;
use QueryNova\Modules\Schema\Infrastructure\WordPressContentSnapshot;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaHead implements HookSubscriberInterface {

    public function __construct(
        private readonly SchemaDocumentBuilder $builder,
        private readonly SchemaRuleStore $rules,
        private readonly WordPressContentSnapshot $snapshots,
        private readonly FeatureRegistry $features,
        private readonly EnvironmentInterface $environment,
    ) {
    }

    public function hooks(): array {
        return [
            'wp_head' => [ 'render', 20, 0 ],
        ];
    }

    public function hookType( string $hook ): string {
        unset( $hook );

        return 'action';
    }

    public function render(): void {
        if ( ! $this->features->isEnabled( 'querynova.schema', $this->environment ) ) {
            return;
        }
        $rules    = $this->rules->all();
        $snapshot = $this->snapshots->current( $rules );
        if ( $snapshot === null ) {
            return;
        }
        $json = $this->builder->build( $snapshot, $rules )->json();
        if ( $json === '' || $json === '{}' ) {
            return;
        }
        echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG escapes markup inside the graph.
    }
}
