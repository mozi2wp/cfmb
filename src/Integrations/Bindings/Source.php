<?php
/**
 * The cfmb/field Block Bindings source.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Bindings;

use CFMB\Display\Formatter;
use CFMB\Display\Resolver;
use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Lets core blocks show field values: a paragraph or heading shows a text
 * field, a button links to a URL field, an image shows an image field.
 *
 *     <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"cfmb/field","args":{"field":"price"}}}}} -->
 *
 * Arguments: field (or key, which the block editor's bindings panel sets)
 * and object (see Resolver; the loop post by default). Values follow the
 * same visibility rules as the shortcode.
 */
class Source {

	public const NAME = 'cfmb/field';

	/**
	 * Source lookup.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Value formatting.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param Resolver  $resolver  Source lookup.
	 * @param Formatter $formatter Value formatting.
	 */
	public function __construct( Resolver $resolver, Formatter $formatter ) {
		$this->resolver  = $resolver;
		$this->formatter = $formatter;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add' ) );
	}

	/**
	 * Register the source where WordPress supports bindings.
	 *
	 * @return void
	 */
	public function add(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			self::NAME,
			array(
				'label'              => __( 'CFMB fields', 'cfmb' ),
				'get_value_callback' => array( $this, 'value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Value for one bound attribute.
	 *
	 * @param array<string, mixed> $args      Source arguments.
	 * @param WP_Block             $block     Block instance.
	 * @param string               $attribute Bound attribute name.
	 * @return mixed Null when the field can't be shown, so the block keeps its own content.
	 */
	public function value( $args, $block, $attribute ) {
		$args    = is_array( $args ) ? $args : array();
		$name    = (string) ( $args['field'] ?? $args['key'] ?? '' );
		$post_id = $block instanceof WP_Block ? (int) ( $block->context['postId'] ?? 0 ) : 0;
		$found   = $this->resolver->find( $name, (string) ( $args['object'] ?? '' ), $post_id );

		if ( ! $found || ! $this->formatter->supports( $found['field'] ) ) {
			return null;
		}

		$parts = $this->formatter->parts( $found['field'], $found['group'], $found['ref'], array( 'size' => sanitize_key( (string) ( $args['size'] ?? '' ) ) ) );

		return self::pick( $parts, (string) $attribute );
	}

	/**
	 * The part of a value an attribute takes. HTML-attribute values (url, alt,
	 * title) are plain, because core escapes them when it sets the attribute.
	 * Rich-text values are inserted as HTML, so they are escaped here.
	 *
	 * @param array<string, mixed> $parts     Value parts from Formatter::parts().
	 * @param string               $attribute Attribute name.
	 * @return mixed
	 */
	public static function pick( array $parts, string $attribute ) {
		switch ( $attribute ) {
			case 'url':
			case 'href':
				return esc_url_raw( (string) $parts['url'] );
			case 'alt':
				return (string) $parts['alt'];
			case 'title':
				return (string) $parts['title'];
			case 'id':
				return $parts['id'] ? (int) $parts['id'] : null;
			case 'content':
			case 'caption':
				return (string) $parts['html'];
			default:
				return esc_html( (string) $parts['text'] );
		}
	}
}
