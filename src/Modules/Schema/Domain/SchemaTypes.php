<?php
/**
 * Schema.org types the graph and the builder are allowed to emit.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaTypes {

    /**
     * @return list<string>
     */
    public static function all(): array {
        return [
            'WebSite',
            'Organization',
            'Person',
            'WebPage',
            'CollectionPage',
            'Article',
            'BlogPosting',
            'BreadcrumbList',
            'Product',
            'ProductGroup',
            'Offer',
            'AggregateOffer',
            'AggregateRating',
            'Review',
            'Brand',
            'Service',
            'LocalBusiness',
            'MedicalOrganization',
            'Physician',
            'SoftwareApplication',
            'Course',
            'Event',
            'VideoObject',
        ];
    }

    public static function allows( string $type ): bool {
        return in_array( $type, self::all(), true );
    }
}
