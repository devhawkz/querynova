<?php
/**
 * Connected schema graph and builder rule tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Schema\Application\ConnectedGraphFactory;
use QueryNova\Modules\Schema\Application\SchemaDocumentBuilder;
use QueryNova\Modules\Schema\Application\SchemaRuleCodec;
use QueryNova\Modules\Schema\Application\SchemaRuleCompiler;
use QueryNova\Modules\Schema\Domain\CommerceFacts;
use QueryNova\Modules\Schema\Domain\ContentSnapshot;
use QueryNova\Modules\Schema\Domain\PropertyMapping;
use QueryNova\Modules\Schema\Domain\RuleCondition;
use QueryNova\Modules\Schema\Domain\SchemaGraph;
use QueryNova\Modules\Schema\Domain\SchemaNode;
use QueryNova\Modules\Schema\Domain\SchemaRule;
use QueryNova\Modules\Schema\Domain\SchemaTypes;
use QueryNova\Modules\Schema\Infrastructure\OptionSchemaRuleStore;

final class SchemaGraphTest extends TestCase {

    private SchemaDocumentBuilder $builder;

    protected function setUp(): void {
        $this->builder                = new SchemaDocumentBuilder( new ConnectedGraphFactory(), new SchemaRuleCompiler() );
        $GLOBALS['querynova_options'] = [];
    }

    public function testPostGraphConnectsWebsiteArticleAndAuthorById(): void {
        $graph = $this->builder->build( $this->post(), [] );
        $site  = $this->node( $graph, 'WebSite' );
        $org   = $this->node( $graph, 'Organization' );
        $post  = $this->node( $graph, 'BlogPosting' );

        self::assertSame( [ '@id' => $org['@id'] ], $site['publisher'] );
        self::assertSame( [ '@id' => 'https://example.test/buying-guide#webpage' ], $post['mainEntityOfPage'] );
        self::assertSame( [ '@id' => $org['@id'] ], $post['publisher'] );
        self::assertSame( 'Ada', $graph->find( 'https://example.test/buying-guide#webpage-author' )?->document()['name'] );
        self::assertCount( 1, array_filter( $graph->types(), static fn ( string $type ): bool => $type === 'Organization' ) );
        self::assertContains( 'BreadcrumbList', $graph->types() );
    }

    public function testMissingRatingIsOmitted(): void {
        $graph   = $this->builder->build( $this->product( new CommerceFacts( name: 'Welder', price: '199.00', currency: 'USD' ) ), [] );
        $product = $this->node( $graph, 'Product' );

        self::assertArrayNotHasKey( 'aggregateRating', $product );
        self::assertNotContains( 'AggregateRating', $graph->types() );
        self::assertSame( '199.00', $graph->findByType( 'Offer' )?->document()['price'] );
    }

    public function testProductLinksOfferBrandRatingAndReview(): void {
        $graph   = $this->builder->build(
            $this->product(
                new CommerceFacts(
                    name: 'Welder',
                    sku: 'W-1',
                    price: '199.00',
                    currency: 'USD',
                    availability: 'https://schema.org/InStock',
                    brand: 'Northwind',
                    ratingValue: 4.5,
                    reviewCount: 1,
                    bestRating: 5,
                    reviews: [
                        [
                            'author' => 'Ada',
                            'body'   => 'Solid torch',
                            'rating' => 4.5,
                        ],
                    ],
                )
            ),
            []
        );
        $product = $this->node( $graph, 'Product' );

        self::assertSame( [ '@id' => $product['@id'] . '-offer' ], $product['offers'] );
        self::assertSame( [ '@id' => $product['@id'] . '-brand' ], $product['brand'] );
        self::assertSame( '4.5', $graph->findByType( 'AggregateRating' )?->document()['ratingValue'] );
        self::assertSame( 'Ada', $graph->find( $product['@id'] . '-review-1-author' )?->document()['name'] );
        self::assertSame( [ '@id' => $product['@id'] ], $graph->findByType( 'WebPage' )?->document()['mainEntity'] );
    }

    public function testVariationsKeepMeasuredPricesOnTheAggregateOffer(): void {
        $graph = $this->builder->build(
            $this->product(
                new CommerceFacts(
                    name: 'Welder',
                    currency: 'USD',
                    variations: [
                        [
                            'sku'          => 'A',
                            'name'         => 'Small',
                            'price'        => '10',
                            'currency'     => 'USD',
                            'availability' => 'https://schema.org/InStock',
                        ],
                        [
                            'sku'          => 'B',
                            'name'         => 'Large',
                            'price'        => '30',
                            'currency'     => 'USD',
                            'availability' => 'https://schema.org/InStock',
                        ],
                    ],
                )
            ),
            []
        );
        $offer = $this->node( $graph, 'AggregateOffer' );

        self::assertContains( 'ProductGroup', $graph->types() );
        self::assertSame( '10', $offer['lowPrice'] );
        self::assertSame( '30', $offer['highPrice'] );
        self::assertSame( 2, $offer['offerCount'] );
        self::assertNotContains( 'Product', $this->typesExceptVariants( $graph ) );
    }

    public function testBuilderRuleOmitsEmptyCommerceFieldsAndStripsUnknownTokens(): void {
        $without = $this->builder->build( $this->post(), [ $this->courseRule() ] );
        self::assertNull( $without->findByType( 'Course' ) );

        $snapshot = new ContentSnapshot(
            permalink: 'https://example.test/class',
            title: 'Welding class',
            siteName: 'Northwind',
            siteUrl: 'https://example.test',
            kind: 'page',
            custom: [ 'course_name' => 'Welding 101' ],
        );
        $graph    = $this->builder->build( $snapshot, [ $this->courseRule(), $this->serviceRule() ] );
        $course   = $this->node( $graph, 'Course' );

        self::assertSame( 'Welding 101', $course['name'] );
        self::assertSame( 'Welding class', $course['description'] );
        self::assertSame( [ '@id' => 'https://example.test/#organization' ], $course['provider'] );
        self::assertSame( 'Audit', $this->node( $graph, 'Service' )['name'] );
        self::assertArrayNotHasKey( 'sku', $this->node( $graph, 'Service' ) );
    }

    public function testEverySupportedTypeCanBeEmittedAndJsonEscapesMarkup(): void {
        $rules = [];
        foreach ( SchemaTypes::all() as $type ) {
            $slug    = strtolower( $type );
            $rules[] = new SchemaRule(
                'extra-' . $slug,
                $type,
                'https://example.test/#' . $slug,
                [],
                [ new PropertyMapping( 'name', 'literal', $type ) ]
            );
        }
        $snapshot   = new ContentSnapshot(
            permalink: 'https://example.test/buying-guide',
            title: '</script><script>alert(1)',
            siteName: 'Northwind',
            siteUrl: 'https://example.test',
            kind: 'collection',
            videoUrl: 'https://youtu.be/abcdefghijk',
            videoTitle: 'Demo',
        );
        $collection = $this->builder->build( $snapshot, [] );
        $graph      = $this->builder->build( $this->post(), $rules );

        foreach ( SchemaTypes::all() as $type ) {
            self::assertContains( $type, $graph->types() );
        }
        self::assertContains( 'CollectionPage', $collection->types() );
        self::assertNotContains( 'BlogPosting', $collection->types() );
        self::assertSame( 'https://youtu.be/abcdefghijk', $collection->findByType( 'VideoObject' )?->document()['embedUrl'] );
        self::assertStringContainsString( '\u003C', $collection->json() );
        self::assertStringNotContainsString( '</script>', $collection->json() );
    }

    public function testCodecRejectsUnknownTypesAndOptionStoreRoundTrips(): void {
        $codec = new SchemaRuleCodec();
        $this->expectException( ValidationException::class );
        try {
            $codec->import(
                [
                    [
                        'type'        => 'NotAType',
                        'id'          => 'bad',
                        'id_template' => 'https://example.test/#x',
                    ],
                ]
            );
        } finally {
            $store = new OptionSchemaRuleStore( new SchemaRuleCodec() );
            $store->replace( [ $this->courseRule() ] );
            self::assertSame( 'Course', $store->all()[0]->type() );
        }
    }

    private function post(): ContentSnapshot {
        return new ContentSnapshot(
            permalink: 'https://example.test/buying-guide',
            title: 'Buying guide',
            description: 'How to choose.',
            siteName: 'Northwind',
            siteUrl: 'https://example.test',
            authorName: 'Ada',
            publishedAt: '2026-09-01T00:00:00+00:00',
            modifiedAt: '2026-09-02T00:00:00+00:00',
        );
    }

    private function product( CommerceFacts $commerce ): ContentSnapshot {
        return new ContentSnapshot(
            permalink: 'https://example.test/product/welder',
            title: 'Welder',
            description: 'Shop welder',
            siteName: 'Northwind',
            siteUrl: 'https://example.test',
            kind: 'product',
            commerce: $commerce,
        );
    }

    private function courseRule(): SchemaRule {
        return new SchemaRule(
            'course-1',
            'Course',
            '%%permalink%%#course',
            [ new RuleCondition( 'custom', 'course_name', 'exists', '' ) ],
            [
                new PropertyMapping( 'name', 'custom', 'course_name' ),
                new PropertyMapping( 'description', 'template', '%%title%% %%missing%%' ),
                new PropertyMapping( 'provider', 'link', '%%site_url%%/#organization' ),
            ]
        );
    }

    private function serviceRule(): SchemaRule {
        return new SchemaRule(
            'service-1',
            'Service',
            '%%permalink%%#service',
            [],
            [
                new PropertyMapping( 'name', 'literal', 'Audit' ),
                new PropertyMapping( 'sku', 'woocommerce', 'sku' ),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function node( SchemaGraph $graph, string $type ): array {
        $node = $graph->findByType( $type );
        self::assertInstanceOf( SchemaNode::class, $node );

        return $node->document();
    }

    /**
     * @return list<string>
     */
    private function typesExceptVariants( SchemaGraph $graph ): array {
        $types = [];
        foreach ( $graph->toArray()['@graph'] as $document ) {
            $id = $document['@id'] ?? '';
            if ( is_string( $id ) && str_contains( $id, '-variant-' ) ) {
                continue;
            }
            if ( is_string( $document['@type'] ?? null ) ) {
                $types[] = $document['@type'];
            }
        }

        return $types;
    }
}
