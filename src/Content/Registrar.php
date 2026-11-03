<?php
/**
 * Registers post types, taxonomies and options pages defined in CFMB.
 *
 * @package CFMB
 */

namespace CFMB\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Turns active definitions into WordPress post types, taxonomies and options pages.
 */
class Registrar {

	/**
	 * Option that asks for a rewrite rules flush on the next request.
	 */
	private const FLUSH_OPTION = 'cfmb_flush_rewrite_rules';

	/**
	 * Names registered by CFMB this request, by kind.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $owned = array(
		'post_type' => array(),
		'taxonomy'  => array(),
	);

	/**
	 * Post type definitions.
	 *
	 * @var Post_Type_Repository
	 */
	private Post_Type_Repository $post_types;

	/**
	 * Taxonomy definitions.
	 *
	 * @var Taxonomy_Repository
	 */
	private Taxonomy_Repository $taxonomies;

	/**
	 * Options page definitions.
	 *
	 * @var Options_Page_Repository
	 */
	private Options_Page_Repository $options_pages;

	/**
	 * Options page registry.
	 *
	 * @var Options_Pages
	 */
	private Options_Pages $pages;

	/**
	 * Names skipped because something else registered them first, by kind.
	 *
	 * @var array<string, string[]>
	 */
	private array $skipped = array();

	/**
	 * Constructor.
	 *
	 * @param Post_Type_Repository    $post_types    Post type definitions.
	 * @param Taxonomy_Repository     $taxonomies    Taxonomy definitions.
	 * @param Options_Page_Repository $options_pages Options page definitions.
	 * @param Options_Pages           $pages         Options page registry.
	 */
	public function __construct( Post_Type_Repository $post_types, Taxonomy_Repository $taxonomies, Options_Page_Repository $options_pages, Options_Pages $pages ) {
		$this->post_types    = $post_types;
		$this->taxonomies    = $taxonomies;
		$this->options_pages = $options_pages;
		$this->pages         = $pages;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_content' ), 5 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );
	}

	/**
	 * Whether CFMB registered a post type or taxonomy with this name.
	 *
	 * @param string $kind post_type or taxonomy.
	 * @param string $name Name.
	 * @return bool
	 */
	public static function owns( string $kind, string $name ): bool {
		return isset( self::$owned[ $kind ][ $name ] );
	}

	/**
	 * Register every active definition.
	 *
	 * @return void
	 */
	public function register_content(): void {
		$post_schema = $this->post_types->schema();

		foreach ( $this->post_types->active() as $item ) {
			$name = (string) $item['name'];

			if ( '' === $name || ( post_type_exists( $name ) && ! self::owns( 'post_type', $name ) ) ) {
				$this->skipped['post_type'][] = $name;
				continue;
			}

			/**
			 * Filters the arguments of a post type defined in CFMB before it is registered.
			 *
			 * @param array<string, mixed> $args register_post_type() arguments.
			 * @param array<string, mixed> $item Definition.
			 */
			$args = apply_filters( 'cfmb/post_type/args', $post_schema->build_args( $item ), $item ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			if ( ! is_wp_error( register_post_type( $name, (array) $args ) ) ) {
				self::$owned['post_type'][ $name ] = (string) $item['key'];
			}
		}

		$taxonomy_schema = $this->taxonomies->schema();

		foreach ( $this->taxonomies->active() as $item ) {
			$name = (string) $item['name'];

			if ( '' === $name || ( taxonomy_exists( $name ) && ! self::owns( 'taxonomy', $name ) ) ) {
				$this->skipped['taxonomy'][] = $name;
				continue;
			}

			/**
			 * Filters the arguments of a taxonomy defined in CFMB before it is registered.
			 *
			 * @param array<string, mixed> $args register_taxonomy() arguments.
			 * @param array<string, mixed> $item Definition.
			 */
			$args = apply_filters( 'cfmb/taxonomy/args', $taxonomy_schema->build_args( $item ), $item ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			if ( $taxonomy_schema instanceof Taxonomy_Schema && ! is_wp_error( register_taxonomy( $name, $taxonomy_schema->object_types( $item ), (array) $args ) ) ) {
				self::$owned['taxonomy'][ $name ] = (string) $item['key'];
			}
		}

		$page_schema = $this->options_pages->schema();

		foreach ( $this->options_pages->active() as $item ) {
			if ( ! $this->pages->get( (string) $item['name'] ) ) {
				$this->pages->add( $page_schema->build_args( $item ) );
			}
		}
	}

	/**
	 * Names that could not be registered because they were taken.
	 *
	 * @return array<string, string[]>
	 */
	public function skipped(): array {
		return $this->skipped;
	}

	/**
	 * Ask for a rewrite rules flush on the next request, once the changed
	 * post types and taxonomies are registered.
	 *
	 * @return void
	 */
	public static function request_rewrite_flush(): void {
		update_option( self::FLUSH_OPTION, 1, true );
	}

	/**
	 * Flush rewrite rules if a definition changed.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( ! get_option( self::FLUSH_OPTION ) ) {
			return;
		}

		delete_option( self::FLUSH_OPTION );
		flush_rewrite_rules( false );
	}
}
