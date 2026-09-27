<?php
/**
 * Validation exception.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ValidationException extends QueryNovaException {

    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct( $message );
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array {
        return $this->errors;
    }
}
