<?php
/**
 * Bricks integration: dynamic data tags and Field Loop query types.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Bricks;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Field_Source;
use CFMB\Integrations\Builders\Loop_Items;
use CFMB\Integrations\Builders\Tag_Values;
use WP_Post;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Tags look like `{cfmb_price}`, `{cfmb_items.title}` (inside a Field Loop
 * over "items") or `{cfmb_logo:object=option}`. Arguments follow colons:
 *
 * - `plain`: text without HTML
 * - `url`: the field's URL (link, file, permalink)
 * - `date=F j, Y`: a date in a PHP format (must be the last argument)
 * - `object=…`: where to read from (see Resolver), e.g. option, term_5
 * - `size=medium`: image size for image elements
 *
 * Loop item facts use `{cfmb_item_index}`, `_count`, `_layout`, `_image`,
 * `_image_url`, `_image_alt`, `_image_title`, `_image_caption`, `_post_url`,
 * `_title` and `_url`.
 *
 * Every loopable field and every Connection is also a Bricks query type,
 * "CFMB: Items", so the native query loop on any element repeats it once per
 * row, image, post, term or user.
 *
 * All hooks are Bricks filters and only fire when Bricks is active.
 */
class Bricks {

	public const PREFIX = 'cfmb_';

	/**
	 * Query type prefix. The field path follows.
	 */
	public const QUERY_PREFIX = 'cfmb_loop:';

	/**
	 * Loop item tag suffixes and the paths Tag_Values reads.
	 */
	private const ITEM_TAGS = array(
		'item_index'         => '@index',
		'item_count'         => '@count',
		'item_layout'        => '@layout',
		'item_image'         => '@image',
		'item_image_url'     => '@image_url',
		'item_image_alt'     => '@image_alt',
		'item_image_title'   => '@image_title',
		'item_image_caption' => '@image_caption',
		'item_post_url'      => '@post_url',
		'item_title'         => '@title',
		'item_url'           => '@url',
	);

	/**
	 * Field choices.
	 *
	 * @var Field_Catalog
	 */
	private Field_Catalog $catalog;

	/**
	 * Values.
	 *
	 * @var Tag_Values
	 */
	private Tag_Values $values;

	/**
	 * Loop items.
	 *
	 * @var Loop_Items
	 */
	private Loop_Items $loops;

	/**
	 * Item open per running Bricks query, by query ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $open = array();

	/**
	 * Constructor.
	 *
	 * @param Field_Catalog $catalog Field choices.
	 * @param Tag_Values    $values  Values.
	 * @param Loop_Items    $loops   Loop items.
	 */
	public function __construct( Field_Catalog $catalog, Tag_Values $values, Loop_Items $loops ) {
		$this->catalog = $catalog;
		$this->values  = $values;
		$this->loops   = $loops;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'bricks/dynamic_tags_list', array( $this, 'add_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 10, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 10, 3 );
		add_filter( 'bricks/frontend/render_data', array( $this, 'render_content' ), 10, 2 );

		add_filter( 'bricks/setup/control_options', array( $this, 'add_query_types' ) );
		add_filter( 'bricks/query/run', array( $this, 'run_query' ), 10, 2 );
		add_filter( 'bricks/query/loop_object', array( $this, 'loop_object' ), 10, 3 );
		add_action( 'bricks/query/after_loop', array( $this, 'after_loop' ), 10, 1 );
	}

	/**
	 * Add CFMB tags to the dynamic data picker.
	 *
	 * @param mixed $tags Registered tags.
	 * @return mixed
	 */
	public function add_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			return $tags;
		}

		$group = __( 'CFMB', 'cfmb' );

		foreach ( $this->catalog->grouped( 'text' ) as $section ) {
			$suffix = $this->catalog->is_item_section( $section ) ? '' : $this->object_suffix( $section );

			foreach ( $section['options'] as $path => $label ) {
				$path = (string) $path;
				$name = '@' === $path[0] ? $this->item_tag_for( $path ) : self::PREFIX . $path . $suffix;

				if ( '' === $name ) {
					continue;
				}

				$tags[] = array(
					'name'  => '{' . $name . '}',
					/* translators: 1: Field Group title, 2: field label. */
					'label' => sprintf( __( '%1$s: %2$s', 'cfmb' ), $section['label'], $label ),
					'group' => $group,
				);
			}
		}

		foreach ( array( 'item_image', 'item_image_url', 'item_post_url' ) as $extra ) {
			$tags[] = array(
				'name'  => '{' . self::PREFIX . $extra . '}',
				'label' => $this->item_label( $extra ),
				'group' => $group,
			);
		}

		return $tags;
	}

	/**
	 * Value of one tag.
	 *
	 * @param mixed  $tag     Tag without braces.
	 * @param mixed  $post    Post Bricks is rendering.
	 * @param string $context text, link, image, media.
	 * @return mixed
	 */
	public function render_tag( $tag, $post = null, $context = 'text' ) {
		if ( ! is_string( $tag ) ) {
			return $tag;
		}

		$parsed = Tag_Parser::parse( trim( $tag, '{}' ), self::PREFIX, self::ITEM_TAGS );

		if ( ! $parsed ) {
			return $tag;
		}

		return $this->value( $parsed, $post, (string) $context );
	}

	/**
	 * Replace CFMB tags inside a string.
	 *
	 * @param mixed  $content Content.
	 * @param mixed  $post    Post Bricks is rendering.
	 * @param string $context Render context.
	 * @return mixed
	 */
	public function render_content( $content, $post = null, $context = 'text' ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{' . self::PREFIX ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/\{(' . preg_quote( self::PREFIX, '/' ) . '[^{}"]+)\}/',
			function ( array $match ) use ( $post, $context ) {
				$value = $this->render_tag( $match[1], $post, is_string( $context ) && 'link' === $context ? 'link' : 'text' );

				if ( is_array( $value ) ) {
					$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
				}

				return is_string( $value ) && $value !== $match[1] ? $value : '';
			},
			$content
		);
	}

	/**
	 * Add one query type per loopable field.
	 *
	 * @param mixed $options Control options.
	 * @return mixed
	 */
	public function add_query_types( $options ) {
		if ( ! is_array( $options ) || ! isset( $options['queryTypes'] ) || ! is_array( $options['queryTypes'] ) ) {
			return $options;
		}

		foreach ( $this->catalog->grouped( 'loop' ) as $section ) {
			$suffix = $this->object_suffix( $section );

			foreach ( $section['options'] as $path => $label ) {
				/* translators: 1: Field Group title, 2: field label. */
				$options['queryTypes'][ self::QUERY_PREFIX . $path . $suffix ] = sprintf( __( 'CFMB: %1$s › %2$s', 'cfmb' ), $section['label'], $label );
			}
		}

		return $options;
	}

	/**
	 * Items for a CFMB query type.
	 *
	 * @param mixed $results Results so far.
	 * @param mixed $query   \Bricks\Query.
	 * @return mixed
	 */
	public function run_query( $results, $query ) {
		$type = is_object( $query ) && isset( $query->object_type ) ? (string) $query->object_type : '';

		if ( 0 !== strpos( $type, self::QUERY_PREFIX ) ) {
			return $results;
		}

		$parsed = Tag_Parser::parse( self::PREFIX . substr( $type, strlen( self::QUERY_PREFIX ) ), self::PREFIX, array() );

		if ( ! $parsed ) {
			return array();
		}

		$settings = is_object( $query ) && isset( $query->settings ) && is_array( $query->settings ) ? $query->settings : array();

		return $this->loops->collect(
			$parsed['path'],
			$this->object_for( $parsed ),
			0,
			array(
				'limit'  => (int) ( $settings['posts_per_page'] ?? 0 ),
				'offset' => (int) ( $settings['offset'] ?? 0 ),
			)
		);
	}

	/**
	 * Open each item as Bricks reaches it, closing the one before.
	 *
	 * @param mixed      $loop_object Current result.
	 * @param int|string $loop_key    Result key.
	 * @param mixed      $query       \Bricks\Query.
	 * @return mixed
	 */
	public function loop_object( $loop_object, $loop_key, $query ) {
		$type = is_object( $query ) && isset( $query->object_type ) ? (string) $query->object_type : '';

		if ( 0 !== strpos( $type, self::QUERY_PREFIX ) || ! is_array( $loop_object ) || ! isset( $loop_object['kind'] ) ) {
			return $loop_object;
		}

		$id = $this->query_id( $query );
		$this->close( $id );
		$this->loops->enter( $loop_object );
		$this->open[ $id ] = $loop_object;

		// Bricks' own tags read WP_Post, WP_Term and WP_User loop objects.
		switch ( $loop_object['kind'] ) {
			case 'post':
				$object = get_post( (int) $loop_object['id'] );
				break;
			case 'term':
				$object = get_term( (int) $loop_object['id'] );
				break;
			case 'user':
				$object = get_userdata( (int) $loop_object['id'] );
				break;
			default:
				$object = null;
		}

		return $object instanceof WP_Post || $object instanceof WP_Term || $object instanceof WP_User ? $object : $loop_object;
	}

	/**
	 * Close the last item when a query finishes.
	 *
	 * @param mixed $query \Bricks\Query.
	 * @return void
	 */
	public function after_loop( $query = null ): void {
		if ( is_object( $query ) ) {
			$this->close( $this->query_id( $query ) );
		}
	}

	/**
	 * The value for a parsed tag in a Bricks context.
	 *
	 * @param array{path: string, args: array<string, string>} $parsed  Parsed tag.
	 * @param mixed                                             $post    Post being rendered.
	 * @param string                                            $context Render context.
	 * @return mixed
	 */
	private function value( array $parsed, $post, string $context ) {
		$path    = $parsed['path'];
		$args    = $parsed['args'];
		$object  = $this->object_for( $parsed );
		$post_id = $post instanceof WP_Post && '' === $object ? (int) $post->ID : 0;
		$size    = sanitize_key( $args['size'] ?? '' );

		if ( 'image' === $context || 'media' === $context || '@image' === $path ) {
			$ids = $this->values->image_ids( $path, $object, $post_id );

			if ( 'text' === $context && '@image' === $path ) {
				$image = $this->values->image( $path, $object, $size, $post_id );

				return $image ? esc_url( $image['url'] ) : '';
			}

			return $ids;
		}

		if ( 'link' === $context || isset( $args['url'] ) ) {
			return esc_url( $this->values->url( $path, $object, array( 'size' => $size ), $post_id ) );
		}

		if ( isset( $args['date'] ) ) {
			return esc_html( $this->values->date( $path, $object, $args['date'], $post_id ) );
		}

		if ( isset( $args['plain'] ) ) {
			return esc_html( $this->values->text( $path, $object, array(), $post_id ) );
		}

		return $this->values->html( $path, $object, array( 'size' => $size ), $post_id );
	}

	/**
	 * The `object` argument: the tag's own, else Bricks' term or user loop
	 * object, else the current post.
	 *
	 * @param array{path: string, args: array<string, string>} $parsed Parsed tag.
	 * @return string
	 */
	private function object_for( array $parsed ): string {
		if ( isset( $parsed['args']['object'] ) ) {
			return $parsed['args']['object'];
		}

		if ( false !== strpos( $parsed['path'], '.' ) || ! class_exists( '\Bricks\Query' ) || ! method_exists( '\Bricks\Query', 'get_loop_object' ) ) {
			return '';
		}

		$loop = \Bricks\Query::get_loop_object();
		$item = Field_Source::nearest_object_item();

		// A CFMB loop over connected terms or users: Field_Source reads its item.
		if ( $item && ( $loop instanceof WP_Term || $loop instanceof WP_User ) && (int) $item['id'] === (int) ( $loop instanceof WP_Term ? $loop->term_id : $loop->ID ) ) {
			return '';
		}

		if ( $loop instanceof WP_Term ) {
			return 'term_' . $loop->term_id;
		}

		return $loop instanceof WP_User ? 'user_' . $loop->ID : '';
	}

	/**
	 * Object argument suffix for fields of a group shown on one options page.
	 *
	 * @param array{label: string, options: array<string, string>, object?: string} $section Catalog section.
	 * @return string
	 */
	private function object_suffix( array $section ): string {
		$object = (string) ( $section['object'] ?? '' );

		return '' !== $object ? ':object=' . str_replace( ':', '/', $object ) : '';
	}

	/**
	 * Tag name for a loop item path.
	 *
	 * @param string $path @ path.
	 * @return string
	 */
	private function item_tag_for( string $path ): string {
		$suffix = array_search( $path, self::ITEM_TAGS, true );

		return false === $suffix ? '' : self::PREFIX . $suffix;
	}

	/**
	 * Label of an extra loop item tag.
	 *
	 * @param string $suffix Tag suffix.
	 * @return string
	 */
	private function item_label( string $suffix ): string {
		$labels = array(
			'item_image'     => __( 'Loop item: Image', 'cfmb' ),
			'item_image_url' => __( 'Loop item: Image URL', 'cfmb' ),
			'item_post_url'  => __( 'Loop item: Post URL', 'cfmb' ),
		);

		return $labels[ $suffix ] ?? $suffix;
	}

	/**
	 * Stable ID for a running query.
	 *
	 * @param object $query \Bricks\Query.
	 * @return string
	 */
	private function query_id( $query ): string {
		$id = isset( $query->element_id ) ? (string) $query->element_id : '';

		return '' !== $id ? $id : (string) spl_object_id( $query );
	}

	/**
	 * Close the item a query left open, and first any item of a query
	 * nested inside it, so the context stack unwinds in order even when
	 * Bricks doesn't report the end of a nested loop.
	 *
	 * @param string $id Query ID.
	 * @return void
	 */
	private function close( string $id ): void {
		if ( ! isset( $this->open[ $id ] ) ) {
			return;
		}

		while ( $this->open ) {
			$last = (string) array_key_last( $this->open );
			$this->loops->leave( $this->open[ $last ] );
			unset( $this->open[ $last ] );

			if ( $last === $id ) {
				return;
			}
		}
	}
}
