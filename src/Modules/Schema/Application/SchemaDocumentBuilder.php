<?php
/**
 * Default connected graph plus saved builder rules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

use QueryNova\Modules\Schema\Domain\ContentSnapshot;
use QueryNova\Modules\Schema\Domain\SchemaGraph;
use QueryNova\Modules\Schema\Domain\SchemaRule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaDocumentBuilder {

    public function __construct(
        private readonly ConnectedGraphFactory $factory,
        private readonly SchemaRuleCompiler $compiler,
    ) {
    }

    /**
     * @param list<SchemaRule> $rules
     */
    public function build( ContentSnapshot $snapshot, array $rules ): SchemaGraph {
        $graph = $this->factory->build( $snapshot );
        $this->compiler->apply( $graph, $snapshot, $rules );

        return $graph;
    }
}
