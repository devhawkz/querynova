<?php
/**
 * Content intelligence. A request analyzes supplied HTML. It does not fetch the URL.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Content\Application\ContentIntelligence;
use QueryNova\Modules\Content\Infrastructure\ContentRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentModule extends AbstractModule {

    private ContentIntelligence $intelligence;

    private ?ContentRepository $store = null;

    public function __construct() {
        $this->intelligence = new ContentIntelligence();
    }

    public function getName(): string {
        return 'content';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.content',
                    'Content intelligence',
                    'content',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::RUN_ANALYSIS ],
                )
            );
        }
        $database    = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->store = new ContentRepository( $database );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/content', [ $this, 'analyze' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/content', [ $this, 'show' ], Capability::RUN_ANALYSIS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function analyze( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new \WP_Error( 'querynova_invalid_content', 'A document payload is required.', [ 'status' => 400 ] );
        }

        return $this->inspect( $params );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $pageId = (int) $request->get_param( 'page_id' );
        if ( $pageId < 1 || ! $this->store instanceof ContentRepository ) {
            return [
                'status' => 'unavailable',
                'note'   => 'No stored content analysis was found.',
            ];
        }
        $row = $this->store->latest( $pageId );
        if ( $row === null ) {
            return [
                'status'  => 'unavailable',
                'page_id' => $pageId,
                'note'    => 'No stored content analysis was found.',
            ];
        }

        return [
            'status'           => 'stored',
            'page_id'          => $pageId,
            'completeness'     => $row['completeness'],
            'information_gain' => $row['information_gain'],
            'note'             => 'Stored observations. Completeness and information gain are not scores.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function inspect( array $input ): array {
        $html = (string) ( $input['html'] ?? '' );
        if ( strlen( $html ) > 200000 ) {
            $html = substr( $html, 0, 200000 );
        }
        $topics = $this->strings( $input['topics'] ?? [] );
        $report = [
            'fetched'           => false,
            'intent'            => $this->intelligence->intent( (string) ( $input['query'] ?? '' ), $this->strings( $input['page_types'] ?? [] ) ),
            'analysis'          => $this->intelligence->analyze( $html, (string) ( $input['keyword'] ?? '' ), $topics ),
            'information_gain'  => $this->intelligence->informationGain( $html ),
            'product_gain'      => $this->intelligence->productGain( $html ),
            'coverage'          => $this->intelligence->coverage( $html, $topics, $this->strings( $input['top_three'] ?? [] ), $this->strings( $input['top_ten'] ?? [] ) ),
            'evidence'          => $this->intelligence->evidence( $html, $this->flags( $input['evidence'] ?? [] ) ),
            'entities'          => $this->intelligence->entities( $html, $this->entities( $input['entities'] ?? [] ) ),
            'topical_authority' => $this->intelligence->topicalAuthority( $this->authority( $input['authority'] ?? [] ) ),
            'link_graph'        => $this->intelligence->linkGraph( $this->pages( $input['pages'] ?? [] ), (string) ( $input['origin'] ?? '' ) ),
            'commerce_links'    => $this->intelligence->commerceLinks( $this->commerce( $input['commerce'] ?? [] ) ),
            'note'              => 'The document was analyzed as supplied. QueryNova did not fetch a URL.',
        ];
        $pageId = (int) ( $input['page_id'] ?? 0 );
        if ( $pageId > 0 && $this->store instanceof ContentRepository ) {
            $report['stored_id'] = $this->store->save( $pageId, $report );
            $mentions            = $report['entities']['mentions'] ?? [];
            if ( is_array( $mentions ) && $mentions !== [] ) {
                /** @var list<array{name: string, type: string, relation: string}> $mentions */
                $this->store->saveEntities( $mentions );
            }
        }

        return $report;
    }

    /**
     * @return list<string>
     */
    private function strings( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $item ) {
            if ( is_string( $item ) && $item !== '' ) {
                $rows[] = $item;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, bool|null>
     */
    private function flags( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $flags = [];
        foreach ( $value as $key => $item ) {
            if ( ! is_string( $key ) ) {
                continue;
            }
            if ( is_bool( $item ) || $item === null ) {
                $flags[ $key ] = $item;
            }
        }

        return $flags;
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function entities( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $name = trim( (string) ( $item['name'] ?? '' ) );
            $type = trim( (string) ( $item['type'] ?? '' ) );
            if ( $name === '' || $type === '' ) {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'type' => $type,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{topic: string, subtopics?: list<string>, pages?: list<string>, products?: list<string>, categories?: list<string>, articles?: list<string>, internal_links?: int|null, rank?: float|null, backlinks?: int|null}>
     */
    private function authority( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $item ) {
            if ( ! is_array( $item ) || trim( (string) ( $item['topic'] ?? '' ) ) === '' ) {
                continue;
            }
            $rows[] = [
                'topic'          => (string) $item['topic'],
                'subtopics'      => $this->strings( $item['subtopics'] ?? [] ),
                'pages'          => $this->strings( $item['pages'] ?? [] ),
                'products'       => $this->strings( $item['products'] ?? [] ),
                'categories'     => $this->strings( $item['categories'] ?? [] ),
                'articles'       => $this->strings( $item['articles'] ?? [] ),
                'internal_links' => isset( $item['internal_links'] ) ? (int) $item['internal_links'] : null,
                'rank'           => isset( $item['rank'] ) ? (float) $item['rank'] : null,
                'backlinks'      => isset( $item['backlinks'] ) ? (int) $item['backlinks'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{url: string, links: list<string>, depth?: int|null, anchors?: list<string>, broken?: list<string>}>
     */
    private function pages( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $item ) {
            if ( ! is_array( $item ) || trim( (string) ( $item['url'] ?? '' ) ) === '' ) {
                continue;
            }
            $row = [
                'url'   => (string) $item['url'],
                'links' => $this->strings( $item['links'] ?? [] ),
            ];
            if ( array_key_exists( 'depth', $item ) ) {
                $row['depth'] = $item['depth'] === null ? null : (int) $item['depth'];
            }
            if ( array_key_exists( 'anchors', $item ) ) {
                $row['anchors'] = $this->strings( $item['anchors'] ?? [] );
            }
            if ( array_key_exists( 'broken', $item ) ) {
                $row['broken'] = $this->strings( $item['broken'] ?? [] );
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{type: string, name: string, url: string}>
     */
    private function commerce( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $type = trim( (string) ( $item['type'] ?? '' ) );
            $name = trim( (string) ( $item['name'] ?? '' ) );
            $url  = trim( (string) ( $item['url'] ?? '' ) );
            if ( $type === '' || $name === '' || $url === '' ) {
                continue;
            }
            $rows[] = [
                'type' => $type,
                'name' => $name,
                'url'  => $url,
            ];
        }

        return $rows;
    }
}
