<?php
/**
 * Builds the default connected graph for a page.
 *
 * Nodes reference each other by @id. Ratings are omitted when they were not measured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

use QueryNova\Modules\Schema\Domain\CommerceFacts;
use QueryNova\Modules\Schema\Domain\ContentSnapshot;
use QueryNova\Modules\Schema\Domain\SchemaGraph;
use QueryNova\Modules\Schema\Domain\SchemaNode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ConnectedGraphFactory {

    public function build( ContentSnapshot $snapshot ): SchemaGraph {
        $graph          = new SchemaGraph();
        $organizationId = $this->fragment( $snapshot->siteUrl(), 'organization', true );
        $websiteId      = $this->fragment( $snapshot->siteUrl(), 'website', true );
        $pageId         = $this->fragment( $snapshot->permalink(), 'webpage', false );
        $this->addOrganization( $graph, $snapshot, $organizationId );
        $this->addWebSite( $graph, $snapshot, $websiteId, $organizationId );
        $this->addPage( $graph, $snapshot, $pageId, $websiteId );
        $this->addBreadcrumbs( $graph, $snapshot, $pageId );
        $this->addArticle( $graph, $snapshot, $pageId, $organizationId );
        $this->addCommerce( $graph, $snapshot, $pageId );
        $this->addVideo( $graph, $snapshot, $pageId );

        return $graph;
    }

    private function addOrganization( SchemaGraph $graph, ContentSnapshot $snapshot, string $id ): void {
        if ( $id === '' || $snapshot->siteName() === '' ) {
            return;
        }
        $node = new SchemaNode( $id, 'Organization' );
        $node->text( 'name', $snapshot->siteName() );
        $node->text( 'url', $this->base( $snapshot->siteUrl() ) );
        $graph->add( $node );
    }

    private function addWebSite( SchemaGraph $graph, ContentSnapshot $snapshot, string $id, string $organizationId ): void {
        if ( $id === '' || $snapshot->siteName() === '' ) {
            return;
        }
        $node = new SchemaNode( $id, 'WebSite' );
        $node->text( 'name', $snapshot->siteName() );
        $node->text( 'url', $this->base( $snapshot->siteUrl() ) );
        $node->reference( 'publisher', $organizationId );
        $graph->add( $node );
    }

    private function addPage( SchemaGraph $graph, ContentSnapshot $snapshot, string $id, string $websiteId ): void {
        if ( $id === '' ) {
            return;
        }
        $type = $snapshot->kind() === 'collection' ? 'CollectionPage' : 'WebPage';
        $node = new SchemaNode( $id, $type );
        $node->text( 'name', $snapshot->title() );
        $node->text( 'url', $snapshot->permalink() );
        $node->text( 'description', $snapshot->description() );
        $node->reference( 'isPartOf', $websiteId );
        $graph->add( $node );
    }

    private function addBreadcrumbs( SchemaGraph $graph, ContentSnapshot $snapshot, string $pageId ): void {
        if ( $pageId === '' ) {
            return;
        }
        $crumbs = $snapshot->breadcrumbs();
        if ( $crumbs === [] && $snapshot->siteName() !== '' && $snapshot->title() !== '' ) {
            $crumbs = [
                [
                    'name' => $snapshot->siteName(),
                    'url'  => $this->base( $snapshot->siteUrl() ),
                ],
                [
                    'name' => $snapshot->title(),
                    'url'  => $snapshot->permalink(),
                ],
            ];
        }
        $itemIds  = [];
        $position = 1;
        foreach ( $crumbs as $crumb ) {
            if ( $crumb['name'] === '' || $crumb['url'] === '' ) {
                continue;
            }
            $itemId = $pageId . '-crumb-' . $position;
            $item   = new SchemaNode( $itemId, 'ListItem' );
            $item->wholeNumber( 'position', $position );
            $item->text( 'name', $crumb['name'] );
            $item->text( 'item', $crumb['url'] );
            $graph->add( $item );
            $itemIds[] = $itemId;
            ++$position;
        }
        if ( $itemIds === [] ) {
            return;
        }
        $listId = $pageId . '-breadcrumb';
        $list   = new SchemaNode( $listId, 'BreadcrumbList' );
        $list->references( 'itemListElement', $itemIds );
        $graph->add( $list );
        $page = $graph->find( $pageId );
        if ( $page instanceof SchemaNode ) {
            $page->reference( 'breadcrumb', $listId );
        }
    }

    private function addArticle( SchemaGraph $graph, ContentSnapshot $snapshot, string $pageId, string $organizationId ): void {
        if ( $snapshot->kind() !== 'post' || $pageId === '' || $snapshot->title() === '' ) {
            return;
        }
        $authorId = $this->addPerson( $graph, $pageId . '-author', $snapshot->authorName() );
        $article  = new SchemaNode( $pageId . '-article', 'BlogPosting' );
        $article->text( 'headline', $snapshot->title() );
        $article->text( 'description', $snapshot->description() );
        $article->text( 'datePublished', $snapshot->publishedAt() );
        $article->text( 'dateModified', $snapshot->modifiedAt() );
        $article->text( 'image', $snapshot->image() );
        $article->reference( 'mainEntityOfPage', $pageId );
        $article->reference( 'author', $authorId );
        $article->reference( 'publisher', $organizationId );
        $graph->add( $article );
    }

    private function addCommerce( SchemaGraph $graph, ContentSnapshot $snapshot, string $pageId ): void {
        $commerce = $snapshot->commerce();
        if ( ! $commerce instanceof CommerceFacts || $snapshot->kind() !== 'product' || $pageId === '' ) {
            return;
        }
        if ( count( $commerce->variations() ) >= 2 ) {
            $productId = $this->addProductGroup( $graph, $snapshot, $commerce, $pageId );
        } else {
            $productId = $this->addSimpleProduct( $graph, $snapshot, $commerce, $pageId );
        }
        $page = $graph->find( $pageId );
        if ( $page instanceof SchemaNode ) {
            $page->reference( 'mainEntity', $productId );
        }
    }

    private function addSimpleProduct( SchemaGraph $graph, ContentSnapshot $snapshot, CommerceFacts $commerce, string $pageId ): string {
        $productId = $pageId . '-product';
        $product   = $this->productNode( $productId, 'Product', $snapshot, $commerce );
        $offerId   = $this->addOffer( $graph, $productId . '-offer', $productId, $commerce->price(), $commerce->currency(), $commerce->availability(), $snapshot->permalink() );
        $product->reference( 'offers', $offerId );
        $this->addBrandRatingReviews( $graph, $product, $commerce, $productId );
        $graph->add( $product );

        return $productId;
    }

    private function addProductGroup( SchemaGraph $graph, ContentSnapshot $snapshot, CommerceFacts $commerce, string $pageId ): string {
        $groupId    = $pageId . '-group';
        $group      = $this->productNode( $groupId, 'ProductGroup', $snapshot, $commerce );
        $variantIds = [];
        foreach ( $commerce->variations() as $index => $variation ) {
            $variantId = $groupId . '-variant-' . ( $index + 1 );
            $variant   = new SchemaNode( $variantId, 'Product' );
            $variant->text( 'name', $variation['name'] !== '' ? $variation['name'] : $snapshot->title() );
            $variant->text( 'sku', $variation['sku'] );
            $offerId = $this->addOffer(
                $graph,
                $variantId . '-offer',
                $variantId,
                $variation['price'],
                $variation['currency'] !== '' ? $variation['currency'] : $commerce->currency(),
                $variation['availability'],
                $snapshot->permalink()
            );
            $variant->reference( 'offers', $offerId );
            $graph->add( $variant );
            $variantIds[] = $variantId;
        }
        $group->references( 'hasVariant', $variantIds );
        $group->reference( 'offers', $this->addAggregateOffer( $graph, $groupId, $commerce ) );
        $this->addBrandRatingReviews( $graph, $group, $commerce, $groupId );
        $graph->add( $group );

        return $groupId;
    }

    private function productNode( string $id, string $type, ContentSnapshot $snapshot, CommerceFacts $commerce ): SchemaNode {
        $node = new SchemaNode( $id, $type );
        $node->text( 'name', $commerce->name() !== '' ? $commerce->name() : $snapshot->title() );
        $node->text( 'sku', $commerce->sku() );
        $node->text( 'description', $snapshot->description() );
        $node->text( 'image', $snapshot->image() );
        $node->text( 'url', $snapshot->permalink() );

        return $node;
    }

    private function addOffer( SchemaGraph $graph, string $offerId, string $productId, string $price, string $currency, string $availability, string $url ): string {
        if ( $price === '' && $availability === '' ) {
            return '';
        }
        $offer = new SchemaNode( $offerId, 'Offer' );
        $offer->text( 'price', $price );
        $offer->text( 'priceCurrency', $currency );
        $offer->text( 'availability', $availability );
        $offer->text( 'url', $url );
        $offer->reference( 'itemOffered', $productId );
        $graph->add( $offer );

        return $offerId;
    }

    private function addAggregateOffer( SchemaGraph $graph, string $groupId, CommerceFacts $commerce ): string {
        $prices   = [];
        $currency = '';
        $mixed    = false;
        foreach ( $commerce->variations() as $variation ) {
            if ( $variation['price'] === '' || ! is_numeric( $variation['price'] ) ) {
                continue;
            }
            $prices[] = $variation['price'];
            $next     = $variation['currency'] !== '' ? $variation['currency'] : $commerce->currency();
            if ( $currency === '' ) {
                $currency = $next;
            } elseif ( $next !== '' && $next !== $currency ) {
                $mixed = true;
            }
        }
        if ( $prices === [] || $mixed ) {
            return '';
        }
        $low  = $prices[0];
        $high = $prices[0];
        foreach ( $prices as $price ) {
            if ( (float) $price < (float) $low ) {
                $low = $price;
            }
            if ( (float) $price > (float) $high ) {
                $high = $price;
            }
        }
        $offerId = $groupId . '-offers';
        $offer   = new SchemaNode( $offerId, 'AggregateOffer' );
        $offer->text( 'lowPrice', $low );
        $offer->text( 'highPrice', $high );
        $offer->text( 'priceCurrency', $currency );
        $offer->wholeNumber( 'offerCount', count( $prices ) );
        $graph->add( $offer );

        return $offerId;
    }

    private function addBrandRatingReviews( SchemaGraph $graph, SchemaNode $product, CommerceFacts $commerce, string $productId ): void {
        if ( $commerce->brand() !== '' ) {
            $brandId = $productId . '-brand';
            $brand   = new SchemaNode( $brandId, 'Brand' );
            $brand->text( 'name', $commerce->brand() );
            $graph->add( $brand );
            $product->reference( 'brand', $brandId );
        }
        $rating = $commerce->ratingValue();
        $count  = $commerce->reviewCount();
        if ( $rating !== null && $count !== null && $count > 0 ) {
            $ratingId = $productId . '-rating';
            $node     = new SchemaNode( $ratingId, 'AggregateRating' );
            $node->text( 'ratingValue', $this->number( $rating ) );
            $node->wholeNumber( 'reviewCount', $count );
            if ( $commerce->bestRating() !== null ) {
                $node->wholeNumber( 'bestRating', $commerce->bestRating() );
            }
            $graph->add( $node );
            $product->reference( 'aggregateRating', $ratingId );
        }
        $reviewIds = [];
        foreach ( $commerce->reviews() as $index => $review ) {
            if ( $review['body'] === '' ) {
                continue;
            }
            $reviewId = $productId . '-review-' . ( $index + 1 );
            $node     = new SchemaNode( $reviewId, 'Review' );
            $node->text( 'reviewBody', $review['body'] );
            $authorId = $this->addPerson( $graph, $reviewId . '-author', $review['author'] );
            $node->reference( 'author', $authorId );
            if ( $review['rating'] !== null ) {
                $ratingNode = [
                    '@type'       => 'Rating',
                    'ratingValue' => $this->number( $review['rating'] ),
                ];
                if ( $commerce->bestRating() !== null ) {
                    $ratingNode['bestRating'] = $commerce->bestRating();
                }
                $node->object( 'reviewRating', $ratingNode );
            }
            $graph->add( $node );
            $reviewIds[] = $reviewId;
        }
        $product->references( 'review', $reviewIds );
    }

    private function addVideo( SchemaGraph $graph, ContentSnapshot $snapshot, string $pageId ): void {
        if ( $pageId === '' || $snapshot->videoUrl() === '' ) {
            return;
        }
        $videoId = $pageId . '-video';
        $video   = new SchemaNode( $videoId, 'VideoObject' );
        $video->text( 'name', $snapshot->videoTitle() !== '' ? $snapshot->videoTitle() : $snapshot->title() );
        $video->text( 'thumbnailUrl', $snapshot->videoThumbnail() );
        if ( preg_match( '/youtube\\.com|youtu\\.be|vimeo\\.com/i', $snapshot->videoUrl() ) === 1 ) {
            $video->text( 'embedUrl', $snapshot->videoUrl() );
        } else {
            $video->text( 'contentUrl', $snapshot->videoUrl() );
        }
        $graph->add( $video );
        $page = $graph->find( $pageId );
        if ( $page instanceof SchemaNode ) {
            $page->reference( 'video', $videoId );
        }
    }

    private function addPerson( SchemaGraph $graph, string $id, string $name ): string {
        if ( $name === '' ) {
            return '';
        }
        $person = new SchemaNode( $id, 'Person' );
        $person->text( 'name', $name );
        $graph->add( $person );

        return $id;
    }

    private function fragment( string $url, string $name, bool $site ): string {
        $base = $this->base( $url );
        if ( $base === '' ) {
            return '';
        }

        return $site ? $base . '/#' . $name : $base . '#' . $name;
    }

    private function base( string $url ): string {
        return rtrim( $url, '/' );
    }

    private function number( float $value ): string {
        $formatted = rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );

        return $formatted === '' ? '0' : $formatted;
    }
}
