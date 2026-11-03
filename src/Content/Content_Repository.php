<?php
/**
 * Storage for post type, taxonomy and options page definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Key_Generator;
use CFMB\Schema\Local_Definitions;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and saves one kind of content definition.
 *
 * Each definition is a private post: post_title the title, post_name the
 * key, post_status publish/draft the active flag, post_content JSON with
 * the name and settings. Definitions from code or Schema Sync files are
 * merged in; the database copy wins when both exist.
 */
class Content_Repository {

	public const CACHE_GROUP = 'cfmb';

	/**
	 * What is stored here.
	 *
	 * @var Content_Schema
	 */
	private Content_Schema $schema;

	/**
	 * Definitions from code and files.
	 *
	 * @var Local_Definitions
	 */
	private Local_Definitions $local;

	/**
	 * Definitions loaded this request, by key.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $loaded = null;

	/**
	 * Local revision the loaded definitions were built from.
	 *
	 * @var int
	 */
	private int $local_revision = -1;

	/**
	 * Constructor.
	 *
	 * @param Content_Schema    $schema What is stored.
	 * @param Local_Definitions $local  Definitions from code and files.
	 */
	public function __construct( Content_Schema $schema, Local_Definitions $local ) {
		$this->schema = $schema;
		$this->local  = $local;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
		add_action( 'clean_post_cache', array( $this, 'maybe_flush' ), 10, 2 );
		add_filter( 'wp_untrash_post_status', array( $this, 'restore_previous_status' ), 10, 3 );
	}

	/**
	 * The schema for this kind.
	 *
	 * @return Content_Schema
	 */
	public function schema(): Content_Schema {
		return $this->schema;
	}

	/**
	 * Register the private post type that holds the definitions.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$capability = cfmb_admin_capability();

		register_post_type(
			$this->schema->storage_type(),
			array(
				'labels'          => array(
					'name'          => $this->schema->names()['plural'],
					'singular_name' => $this->schema->names()['singular'],
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'can_export'      => true,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => false,
				'capabilities'    => array(
					'edit_post'          => $capability,
					'read_post'          => $capability,
					'delete_post'        => $capability,
					'edit_posts'         => $capability,
					'edit_others_posts'  => $capability,
					'delete_posts'       => $capability,
					'publish_posts'      => $capability,
					'read_private_posts' => $capability,
					'create_posts'       => $capability,
				),
			)
		);
	}

	/**
	 * Every definition: stored ones (active and inactive), then those only
	 * defined in code or files. Each has `source`: db, code or file.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		if ( null === $this->loaded || $this->local->revision() !== $this->local_revision ) {
			$this->local_revision = $this->local->revision();
			$this->loaded         = array();
			$stored       = $this->load_stored();

			foreach ( $stored['items'] as $item ) {
				$this->loaded[ $item['key'] ] = $item;
			}

			foreach ( $this->local->items( $this->schema->kind() ) as $key => $item ) {
				// A trashed database copy still hides the file, so trashing doesn't bring the file version back.
				if ( isset( $this->loaded[ $key ] ) || in_array( $key, $stored['trashed'], true ) ) {
					continue;
				}

				$this->loaded[ $key ] = array_merge(
					$this->schema->normalize( $item ),
					array(
						'id'     => 0,
						'source' => (string) $item['source'],
						'file'   => (string) ( $item['file'] ?? '' ),
					)
				);
			}
		}

		return array_values( $this->loaded );
	}

	/**
	 * Active definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function active(): array {
		return array_values( array_filter( $this->all(), static fn( array $item ) => ! empty( $item['active'] ) ) );
	}

	/**
	 * Definitions in the trash.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function trashed(): array {
		return array_map( array( $this, 'hydrate' ), get_posts( $this->query_args( array( 'trash' ) ) ) );
	}

	/**
	 * Find a definition by key, including trashed ones.
	 *
	 * @param string $key Key.
	 * @return array<string, mixed>|null
	 */
	public function find( string $key ): ?array {
		foreach ( $this->all() as $item ) {
			if ( $item['key'] === $key ) {
				return $item;
			}
		}

		$post = $this->post_for_key( $key, array( 'trash' ) );

		return $post ? $this->hydrate( $post ) : null;
	}

	/**
	 * Names used by definitions other than the given key.
	 *
	 * @param string $except_key Key to leave out.
	 * @return string[]
	 */
	public function names_taken( string $except_key = '' ): array {
		$names = array();

		foreach ( $this->all() as $item ) {
			if ( $item['key'] !== $except_key ) {
				$names[] = (string) $item['name'];
			}
		}

		return $names;
	}

	/**
	 * Create or update a definition from its sanitized form.
	 *
	 * @param array<string, mixed> $item Sanitized definition.
	 * @return array<string, mixed>|WP_Error Saved definition.
	 */
	public function save( array $item ) {
		$item = $this->schema->normalize( $item );
		$post = $this->post_for_key( $item['key'], array( 'publish', 'draft', 'trash' ) );

		$body = array(
			'version'  => 1,
			'name'     => $item['name'],
			'settings' => $item['settings'],
		);

		$postarr = array(
			'post_type'    => $this->schema->storage_type(),
			'post_title'   => $item['title'],
			'post_name'    => $item['key'],
			'post_status'  => $item['active'] ? 'publish' : 'draft',
			'post_content' => wp_slash( (string) wp_json_encode( $body ) ),
		);

		if ( $post ) {
			$postarr['ID'] = $post->ID;

			if ( 'trash' === $post->post_status ) {
				$postarr['post_status'] = 'trash';
				$postarr['post_name']   = $post->post_name;
			}
		}

		$post_id = $this->write_post( $postarr );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->flush();
		$this->request_rewrite_flush();

		$saved = get_post( $post_id );

		if ( ! $saved instanceof WP_Post ) {
			return new WP_Error( 'cfmb_content_not_saved', __( 'That could not be saved. Please try again.', 'cfmb' ) );
		}

		$stored = $this->hydrate( $saved );

		/**
		 * Fires after a Field Group, post type, taxonomy or options page was saved
		 * in the database. Schema Sync writes its file here.
		 *
		 * @param string               $kind    group, post_type, taxonomy, options_page or connection.
		 * @param array<string, mixed> $item    Saved definition.
		 * @param int                  $post_id Storage post ID.
		 */
		do_action( 'cfmb/schema/saved', $this->schema->kind(), $stored, (int) $post_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $stored;
	}

	/**
	 * Move a definition to the trash.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function trash( string $key ): bool {
		$post = $this->post_for_key( $key, array( 'publish', 'draft' ) );

		if ( ! $post ) {
			return false;
		}

		$done = (bool) wp_trash_post( $post->ID );
		$this->flush();
		$this->request_rewrite_flush();

		return $done;
	}

	/**
	 * Restore a trashed definition with its previous active state.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function restore( string $key ): bool {
		$post = $this->post_for_key( $key, array( 'trash' ) );

		if ( ! $post ) {
			return false;
		}

		$done = (bool) wp_untrash_post( $post->ID );
		$this->flush();
		$this->request_rewrite_flush();

		return $done;
	}

	/**
	 * Delete a definition for good. Content made with it (posts, terms, values) is kept.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function delete( string $key ): bool {
		$post = $this->post_for_key( $key, array( 'publish', 'draft', 'trash' ) );

		if ( ! $post ) {
			return false;
		}

		$done = (bool) wp_delete_post( $post->ID, true );
		$this->flush();
		$this->request_rewrite_flush();

		if ( $done ) {
			/**
			 * Fires after a definition was deleted for good. Schema Sync removes its file.
			 *
			 * @param string $kind group, post_type, taxonomy, options_page or connection.
			 * @param string $key  Definition key.
			 */
			do_action( 'cfmb/schema/deleted', $this->schema->kind(), $key ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		}

		return $done;
	}

	/**
	 * Copy a definition. The copy gets a new key, a free name and starts inactive.
	 *
	 * @param string $key Key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function duplicate( string $key ) {
		$source = $this->find( $key );

		if ( ! $source ) {
			return new WP_Error( 'cfmb_content_missing', __( 'That item no longer exists.', 'cfmb' ), array( 'status' => 404 ) );
		}

		$taken  = $this->names_taken();
		$max    = $this->schema->name_max_length();
		$suffix = 2;

		do {
			$name = substr( (string) $source['name'], 0, $max - strlen( '_' . $suffix ) ) . '_' . $suffix;
			++$suffix;
		} while ( in_array( $name, $taken, true ) );

		$copy           = $source;
		$copy['key']    = Key_Generator::make( $this->schema->key_prefix() );
		$copy['name']   = $name;
		$copy['active'] = false;
		$copy['title']  = sprintf(
			/* translators: %s: original title */
			__( '%s (copy)', 'cfmb' ),
			$source['title']
		);

		return $this->save( $copy );
	}

	/**
	 * Keep the active state when a definition leaves the trash.
	 *
	 * @param string $new_status      Status WordPress picked.
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status Status before trashing.
	 * @return string
	 */
	public function restore_previous_status( $new_status, $post_id, $previous_status ) {
		if ( $this->schema->storage_type() !== get_post_type( $post_id ) ) {
			return $new_status;
		}

		return in_array( $previous_status, array( 'publish', 'draft' ), true ) ? $previous_status : $new_status;
	}

	/**
	 * Flush caches when one of our posts changes elsewhere.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function maybe_flush( $post_id, $post ): void {
		if ( $post instanceof WP_Post && $this->schema->storage_type() === $post->post_type ) {
			$this->flush();
		}
	}

	/**
	 * Drop the request cache and invalidate the object cache.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->loaded = null;
		wp_cache_set_last_changed( self::CACHE_GROUP );
	}

	/**
	 * Post types and taxonomies change URLs; options pages and connections don't.
	 *
	 * @return void
	 */
	private function request_rewrite_flush(): void {
		if ( in_array( $this->schema->kind(), array( 'post_type', 'taxonomy' ), true ) ) {
			Registrar::request_rewrite_flush();
		}
	}

	/**
	 * Storage post ID for a key, 0 when not stored.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public function post_id( string $key ): int {
		$post = $this->post_for_key( $key, array( 'publish', 'draft', 'trash' ) );

		return $post ? (int) $post->ID : 0;
	}

	/**
	 * Stored definitions and the keys of trashed ones, from cache or one query.
	 *
	 * @return array{items: array<int, array<string, mixed>>, trashed: string[]}
	 */
	private function load_stored(): array {
		$cache_key = $this->schema->kind() . ':' . wp_cache_get_last_changed( self::CACHE_GROUP );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) && isset( $cached['items'], $cached['trashed'] ) ) {
			return $cached;
		}

		$stored = array(
			'items'   => array(),
			'trashed' => array(),
		);

		foreach ( get_posts( $this->query_args( array( 'publish', 'draft', 'trash' ) ) ) as $post ) {
			if ( 'trash' === $post->post_status ) {
				$stored['trashed'][] = (string) preg_replace( '/__trashed$/', '', $post->post_name );
				continue;
			}

			$stored['items'][] = $this->hydrate( $post );
		}

		wp_cache_set( $cache_key, $stored, self::CACHE_GROUP );

		return $stored;
	}

	/**
	 * Turn a stored post into a definition.
	 *
	 * @param WP_Post $post Storage post.
	 * @return array<string, mixed>
	 */
	private function hydrate( WP_Post $post ): array {
		$body   = json_decode( $post->post_content, true );
		$body   = is_array( $body ) ? $body : array();
		$status = $post->post_status;

		if ( 'trash' === $status ) {
			$status = (string) get_post_meta( $post->ID, '_wp_trash_meta_status', true );
		}

		return array_merge(
			$this->schema->normalize(
				array(
					'key'      => (string) preg_replace( '/__trashed$/', '', $post->post_name ),
					'name'     => (string) ( $body['name'] ?? '' ),
					'title'    => $post->post_title,
					'active'   => 'publish' === $status,
					'settings' => is_array( $body['settings'] ?? null ) ? $body['settings'] : array(),
				)
			),
			array(
				'id'     => (int) $post->ID,
				'source' => 'db',
				'file'   => '',
			)
		);
	}

	/**
	 * Stored post for a key.
	 *
	 * @param string   $key      Key.
	 * @param string[] $statuses Statuses to search.
	 * @return WP_Post|null
	 */
	private function post_for_key( string $key, array $statuses ): ?WP_Post {
		if ( ! Key_Generator::is_valid( $key, $this->schema->key_prefix() ) ) {
			return null;
		}

		$posts = get_posts(
			array_merge(
				$this->query_args( $statuses ),
				array(
					'post_name__in' => array( $key, $key . '__trashed' ),
					'numberposts'   => 1,
				)
			)
		);

		return $posts[0] ?? null;
	}

	/**
	 * Shared get_posts() arguments.
	 *
	 * @param string[] $statuses Statuses.
	 * @return array<string, mixed>
	 */
	private function query_args( array $statuses ): array {
		return array(
			'post_type'              => $this->schema->storage_type(),
			'post_status'            => $statuses,
			'numberposts'            => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);
	}

	/**
	 * Insert or update without kses touching the JSON body. Every value inside
	 * was sanitized setting by setting.
	 *
	 * @param array<string, mixed> $postarr Post data.
	 * @return int|WP_Error
	 */
	private function write_post( array $postarr ) {
		$priority = has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( false !== $priority ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses', $priority );
		}

		$post_id = isset( $postarr['ID'] ) ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );

		if ( false !== $priority ) {
			add_filter( 'content_save_pre', 'wp_filter_post_kses', $priority );
		}

		return $post_id;
	}
}
