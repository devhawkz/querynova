<?php
/**
 * On-page checklist for one supplied document.
 *
 * This does not fetch a URL, save meta, or predict rankings.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

use QueryNova\Modules\Crawler\Application\HtmlInspector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OnPageChecklist {

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public static function assess( array $document ): array {
        $title       = self::text( $document['title'] ?? '' );
        $description = self::text( $document['description'] ?? '' );
        $permalink   = self::text( $document['permalink'] ?? '' );
        $canonical   = self::text( $document['canonical'] ?? '' );
        $index       = self::text( $document['robots_index'] ?? '' );
        $follow      = self::text( $document['robots_follow'] ?? '' );
        $snippet     = self::text( $document['robots_max_snippet'] ?? '' );
        $image       = self::text( $document['robots_max_image_preview'] ?? '' );
        $video       = self::text( $document['robots_max_video_preview'] ?? '' );
        $html        = self::text( $document['html'] ?? '' );
        $keywords    = self::keywords( $document['focus_keywords'] ?? ( $document['focus_keyword'] ?? '' ) );
        $checks      = [
            self::titleCheck( $title ),
            self::descriptionCheck( $description ),
            self::permalinkCheck( $permalink ),
            self::canonicalCheck( $canonical ),
            self::indexCheck( $index ),
            self::followCheck( $follow ),
            self::previewCountCheck( 'max_snippet', 'max-snippet', $snippet ),
            self::imagePreviewCheck( $image ),
            self::previewCountCheck( 'max_video_preview', 'max-video-preview', $video ),
            self::keywordCheck( $keywords, $title, $description, $html ),
            self::contentCheck( $html ),
            self::headingCheck( $html ),
            self::mediaCheck( $html ),
            self::linkCheck( $html ),
            self::readabilityCheck( $html ),
            self::schemaCheck( $html ),
            self::socialCheck( $document, $title, $description ),
        ];

        return [
            'name'              => 'On-page checklist',
            'note'              => 'This checklist does not predict rankings.',
            'permalink_preview' => $permalink,
            'social_preview'    => self::socialPreview( $document, $title, $description ),
            'focus_keywords'    => $keywords,
            'summary'           => self::summary( $checks ),
            'checks'            => $checks,
        ];
    }

    /**
     * @param list<array<string, string>> $checks
     * @return array{passed: int, warning: int, failed: int, info: int}
     */
    private static function summary( array $checks ): array {
        $summary = [
            'passed'  => 0,
            'warning' => 0,
            'failed'  => 0,
            'info'    => 0,
        ];
        foreach ( $checks as $check ) {
            $status = $check['status'];
            if ( isset( $summary[ $status ] ) ) {
                ++$summary[ $status ];
            }
        }

        return $summary;
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function check( string $id, string $area, string $status, string $explanation, string $evidence, string $fix ): array {
        return [
            'id'          => $id,
            'area'        => $area,
            'status'      => $status,
            'explanation' => $explanation,
            'evidence'    => $evidence,
            'how_to_fix'  => $fix,
        ];
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function titleCheck( string $title ): array {
        if ( $title === '' ) {
            return self::check( 'title', 'title', 'failed', 'The title is empty.', 'Title length is 0 characters.', 'Add a title. This check does not write one.' );
        }
        $length = self::length( $title );
        if ( $length < 30 || $length > 60 ) {
            return self::check( 'title', 'title', 'warning', 'QueryNova flags titles outside 30 to 60 characters. This does not predict rankings.', 'Title length is ' . $length . ' characters.', 'Shorten or expand the title if you want it inside that range.' );
        }

        return self::check( 'title', 'title', 'passed', 'The title is present and inside the QueryNova range.', 'Title length is ' . $length . ' characters.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function descriptionCheck( string $description ): array {
        if ( $description === '' ) {
            return self::check( 'description', 'description', 'warning', 'The description is empty.', 'Description length is 0 characters.', 'Add a description. This check does not write one.' );
        }
        $length = self::length( $description );
        if ( $length < 70 || $length > 160 ) {
            return self::check( 'description', 'description', 'warning', 'QueryNova flags descriptions outside 70 to 160 characters. This does not predict rankings.', 'Description length is ' . $length . ' characters.', 'Adjust the description if you want it inside that range.' );
        }

        return self::check( 'description', 'description', 'passed', 'The description is present and inside the QueryNova range.', 'Description length is ' . $length . ' characters.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function permalinkCheck( string $permalink ): array {
        if ( $permalink === '' ) {
            return self::check( 'permalink', 'url', 'info', 'No permalink was supplied.', 'Permalink preview is empty.', 'Open the document to see its permalink. This check does not change the slug.' );
        }
        if ( preg_match( '/\s/', $permalink ) === 1 ) {
            return self::check( 'permalink', 'url', 'failed', 'The permalink contains a space.', $permalink, 'Use an encoded URL. This check does not change the slug.' );
        }
        if ( self::length( $permalink ) > 90 ) {
            return self::check( 'permalink', 'url', 'warning', 'QueryNova flags permalinks longer than 90 characters.', $permalink, 'Review the slug. This check does not change it.' );
        }

        return self::check( 'permalink', 'url', 'passed', 'The permalink was supplied.', $permalink, 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function canonicalCheck( string $canonical ): array {
        if ( $canonical === '' ) {
            return self::check( 'canonical', 'canonical', 'info', 'No canonical override is set. The document URL stays the default.', 'Canonical is empty.', 'Leave it empty unless this document should point somewhere else.' );
        }
        $parts  = wp_parse_url( $canonical );
        $scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return self::check( 'canonical', 'canonical', 'failed', 'The canonical value is not an http or https URL.', $canonical, 'Use an http or https URL, or clear the field.' );
        }

        return self::check( 'canonical', 'canonical', 'info', 'A canonical URL replaces the default URL for this document.', $canonical, 'Keep it only if this document should resolve elsewhere.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function indexCheck( string $index ): array {
        if ( $index === 'noindex' ) {
            return self::check( 'robots_index', 'robots', 'warning', 'noindex can remove this URL from search results.', 'robots index is noindex.', 'Change it only if this URL should be indexed.' );
        }

        return self::check( 'robots_index', 'robots', 'passed', 'The document is not marked noindex.', $index === '' ? 'Index uses the default.' : 'robots index is ' . $index . '.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function followCheck( string $follow ): array {
        if ( $follow === 'nofollow' ) {
            return self::check( 'robots_follow', 'robots', 'info', 'nofollow asks crawlers not to follow links on this document.', 'robots follow is nofollow.', 'Change it only if links on this document should be followed.' );
        }

        return self::check( 'robots_follow', 'robots', 'passed', 'The document is not marked nofollow.', $follow === '' ? 'Follow uses the default.' : 'robots follow is ' . $follow . '.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function previewCountCheck( string $id, string $label, string $value ): array {
        if ( $value === '' || $value === '-1' || preg_match( '/^\d+$/', $value ) === 1 ) {
            return self::check( $id, 'robots', 'passed', $label . ' is empty or a whole number. Empty uses the default.', $value === '' ? $label . ' is empty.' : $label . ' is ' . $value . '.', 'No change is required.' );
        }

        return self::check( $id, 'robots', 'failed', $label . ' must be empty, -1, or a whole number.', $value, 'Clear the field or enter -1 or a whole number.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function imagePreviewCheck( string $value ): array {
        if ( in_array( $value, [ '', 'none', 'standard', 'large' ], true ) ) {
            return self::check( 'max_image_preview', 'robots', 'passed', 'max-image-preview is empty or one of none, standard, or large.', $value === '' ? 'max-image-preview is empty.' : 'max-image-preview is ' . $value . '.', 'No change is required.' );
        }

        return self::check( 'max_image_preview', 'robots', 'failed', 'max-image-preview must be empty, none, standard, or large.', $value, 'Choose one of those values.' );
    }

    /**
     * @param list<string> $keywords
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function keywordCheck( array $keywords, string $title, string $description, string $html ): array {
        if ( $keywords === [] ) {
            return self::check( 'focus_keywords', 'keywords', 'info', 'No focus keywords were supplied.', 'Focus keywords are empty.', 'Add keywords separated by commas. This check does not insert them.' );
        }
        $status = count( $keywords ) > 5 ? 'warning' : 'passed';
        $hits   = [];
        $plain  = strtolower( wp_strip_all_tags( $html ) );
        foreach ( $keywords as $keyword ) {
            $needle = strtolower( $keyword );
            $places = [];
            if ( str_contains( strtolower( $title ), $needle ) ) {
                $places[] = 'title';
            }
            if ( str_contains( strtolower( $description ), $needle ) ) {
                $places[] = 'description';
            }
            if ( $plain !== '' && str_contains( $plain, $needle ) ) {
                $places[] = 'content';
            }
            $hits[] = $keyword . ': ' . ( $places === [] ? 'not found in the supplied text' : implode( ', ', $places ) );
        }
        $explanation = count( $keywords ) > 5
            ? 'More than five focus keywords were supplied. QueryNova keeps five on save.'
            : 'Focus keywords are observations about the supplied text. They do not predict rankings.';

        return self::check( 'focus_keywords', 'keywords', $status, $explanation, implode( '; ', $hits ), 'Edit the keywords in the document. This check does not change the body.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function contentCheck( string $html ): array {
        $words = self::words( $html );
        if ( $words === 0 ) {
            return self::check( 'content', 'content', 'warning', 'No visible words were found in the supplied HTML.', 'Word count is 0.', 'Add the document body. This check does not write content.' );
        }
        if ( $words < HtmlInspector::THIN_WORDS ) {
            return self::check( 'content', 'content', 'warning', 'QueryNova flags documents under ' . HtmlInspector::THIN_WORDS . ' words. This is not a search-engine score.', 'Word count is ' . $words . '.', 'Add useful content if this document should be longer.' );
        }

        return self::check( 'content', 'content', 'passed', 'The supplied document is at least ' . HtmlInspector::THIN_WORDS . ' words.', 'Word count is ' . $words . '.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function headingCheck( string $html ): array {
        preg_match_all( '/<h1\b[^>]*>.*?<\/h1>/is', $html, $matches );
        $count = count( $matches[0] );
        if ( $count === 0 ) {
            return self::check( 'headings', 'headings', 'warning', 'No H1 was found in the supplied HTML.', 'H1 count is 0.', 'Add one H1. This check does not edit the document.' );
        }
        if ( $count > 1 ) {
            return self::check( 'headings', 'headings', 'info', 'More than one H1 was found.', 'H1 count is ' . $count . '.', 'Review the extra H1 elements.' );
        }

        return self::check( 'headings', 'headings', 'passed', 'One H1 was found.', 'H1 count is 1.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function mediaCheck( string $html ): array {
        preg_match_all( '/<img\b[^>]*>/i', $html, $images );
        $tags = $images[0];
        if ( $tags === [] ) {
            return self::check( 'media', 'media', 'info', 'No images were found in the supplied HTML.', 'Image count is 0.', 'Add an image with ALT text if the document needs one. This check does not write ALT text.' );
        }
        $missing = 0;
        foreach ( $tags as $tag ) {
            if ( preg_match( '/\balt\s*=\s*([\'"])(.*?)\1/i', $tag, $alt ) !== 1 || trim( $alt[2] ) === '' ) {
                ++$missing;
            }
        }
        if ( $missing > 0 ) {
            return self::check( 'media', 'media', 'warning', 'Some images have no ALT text. Existing ALT text is left unchanged.', $missing . ' of ' . count( $tags ) . ' images have no ALT text.', 'Add ALT text in the media library. This check does not overwrite manual ALT text.' );
        }

        return self::check( 'media', 'media', 'passed', 'Every supplied image has ALT text.', 'Image count is ' . count( $tags ) . '.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function linkCheck( string $html ): array {
        preg_match_all( '/<a\b[^>]*>/i', $html, $links );
        $count = count( $links[0] );
        if ( $count === 0 ) {
            return self::check( 'links', 'links', 'info', 'No links were found in the supplied HTML. Suggestions stay Suggest Only.', 'Link count is 0.', 'Add a link in the editor if the document needs one. This check does not insert links.' );
        }

        return self::check( 'links', 'links', 'passed', 'Links were found in the supplied HTML. Nothing is inserted.', 'Link count is ' . $count . '.', 'No change is required.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function readabilityCheck( string $html ): array {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ?? '' );
        if ( $text === '' ) {
            return self::check( 'readability', 'readability', 'info', 'Readability is an observation. It does not predict rankings.', 'No sentences were supplied.', 'Add text before reading sentence length.' );
        }
        $parts     = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $sentences = is_array( $parts ) ? count( $parts ) : 0;
        $words     = self::words( $html );
        $average   = $sentences > 0 ? (int) round( $words / $sentences ) : $words;

        return self::check( 'readability', 'readability', 'info', 'Readability is an observation. It does not predict rankings.', 'Average sentence length is ' . $average . ' words across ' . $sentences . ' sentences.', 'Shorten long sentences if you want the text to be easier to read.' );
    }

    /**
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function schemaCheck( string $html ): array {
        if ( stripos( $html, 'application/ld+json' ) !== false ) {
            return self::check( 'schema', 'schema', 'passed', 'A JSON-LD script was found in the supplied HTML.', 'application/ld+json is present.', 'Review the schema on the Schema screen. This check does not save rules.' );
        }

        return self::check( 'schema', 'schema', 'info', 'No JSON-LD script was found in the supplied HTML.', 'application/ld+json is absent.', 'Add schema from the Schema screen if this document needs it.' );
    }

    /**
     * @param array<string, mixed> $document
     * @return array{id: string, area: string, status: string, explanation: string, evidence: string, how_to_fix: string}
     */
    private static function socialCheck( array $document, string $title, string $description ): array {
        $preview = self::socialPreview( $document, $title, $description );
        if ( $preview['title'] === '' && $preview['description'] === '' ) {
            return self::check( 'social', 'social', 'warning', 'The social preview has no title or description.', 'Open Graph title and description are empty.', 'Add a title or an Open Graph title. This check does not publish the document.' );
        }

        return self::check( 'social', 'social', 'passed', 'The social preview has a title or description.', 'X/Twitter card is ' . $preview['twitter_card'] . '.', 'Add an image if the preview should show one.' );
    }

    /**
     * @param array<string, mixed> $document
     * @return array{title: string, description: string, image: string, twitter_card: string}
     */
    private static function socialPreview( array $document, string $title, string $description ): array {
        $ogTitle = self::text( $document['og_title'] ?? '' );
        $ogBody  = self::text( $document['og_description'] ?? '' );
        $card    = self::text( $document['twitter_card'] ?? '' );

        return [
            'title'        => $ogTitle !== '' ? $ogTitle : $title,
            'description'  => $ogBody !== '' ? $ogBody : $description,
            'image'        => self::text( $document['og_image'] ?? '' ),
            'twitter_card' => $card !== '' ? $card : 'summary_large_image',
        ];
    }

    /**
     * @return list<string>
     */
    private static function keywords( mixed $value ): array {
        $raw = is_array( $value ) ? implode( ',', array_map( static fn ( $item ): string => is_string( $item ) ? $item : '', $value ) ) : self::text( $value );
        $out = [];
        foreach ( explode( ',', $raw ) as $part ) {
            $keyword = trim( $part );
            if ( $keyword !== '' && ! in_array( $keyword, $out, true ) ) {
                $out[] = $keyword;
            }
        }

        return $out;
    }

    private static function words( string $html ): int {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ?? '' );
        if ( $text === '' ) {
            return 0;
        }
        $parts = preg_split( '/\s+/', $text );

        return is_array( $parts ) ? count( $parts ) : 0;
    }

    private static function length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
    }

    private static function text( mixed $value ): string {
        return is_string( $value ) ? trim( $value ) : '';
    }
}
