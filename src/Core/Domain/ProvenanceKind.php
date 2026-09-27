<?php
/**
 * Measured, attributed, and estimated values stay distinct.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum ProvenanceKind: string {

    case Measured    = 'MEASURED';
    case Attributed  = 'ATTRIBUTED';
    case Estimated   = 'ESTIMATED';
    case Unavailable = 'UNAVAILABLE';
}
