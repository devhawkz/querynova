<?php
/**
 * Stores schema builder rules in a non-autoloaded option.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Infrastructure;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Schema\Application\SchemaRuleCodec;
use QueryNova\Modules\Schema\Domain\SchemaRuleStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OptionSchemaRuleStore implements SchemaRuleStore {

    public const OPTION = 'querynova_schema_rules';

    public function __construct( private readonly SchemaRuleCodec $codec ) {
    }

    public function all(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        try {
            return $this->codec->import( $stored );
        } catch ( ValidationException ) {
            return [];
        }
    }

    public function replace( array $rules ): void {
        update_option( self::OPTION, $this->codec->export( $rules ), false );
    }
}
