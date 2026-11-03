<?php
/**
 * Field Group storage.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and saves Field Groups.
 *
 * Each group is one private `cfmb_group` post. The post title is the group
 * title, post_name the group key, post_status publish/draft the active flag,
 * menu_order the order and post_content the JSON body (fields, location,
 * settings). One query loads every group.
 *
 * Groups registered in code (cfmb_register_group()) or found in Schema Sync
 * files are merged in. The database copy wins when a key exists in both.
 */
class Group_Repository {

	public const POST_TYPE   = 'cfmb_group';
	public const CACHE_GROUP = 'cfmb';

	/**
	 * Groups loaded during this request, by key.
	 *
	 * @var array<string, Group>|null
	 */
	private ?array $loaded = null;

	/**
	 * Groups from code and files.
	 *
	 * @var Local_Definitions|null
	 */
	private ?Local_Definitions $local;

	/**
	 * Local revision the loaded groups were built from.
	 *
	 * @var int
	 */
	private int $local_revision = -1;

	/**
	 * Constructor.
	 *
	 * @param Local_Definitions|null $local Groups from code and files.
	 */
	public function __construct( ?Local_Definitions $local = null ) {
		$this->local = $local;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'clean_post_cache', array( $this, 'maybe_flush' ), 10, 2 );
		add_filter( 'wp_untrash_post_status', array( $this, 'restore_previous_status' ), 10, 3 );
	}

	/**
	 * Register the private post type that holds groups.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$capability = cfmb_admin_capability();

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Field Groups', 'custom-fields-meta-box' ),
					'singular_name' => __( 'Field Group', 'custom-fields-meta-box' ),
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
	 * All active and inactive groups, sorted by order then title.
	 *
	 * @return Group[]
	 */
	public function all(): array {
		$revision = $this->local ? $this->local->revision() : 0;

		if ( null !== $this->loaded && $revision === $this->local_revision ) {
			return array_values( $this->loaded );
		}

		$this->local_revision = $revision;
		$this->loaded         = array();
		$stored               = $this->load_arrays();

		foreach ( $stored['groups'] as $group ) {
			$this->loaded[ $group['key'] ] = new Group( $group );
		}

		foreach ( $this->local ? $this->local->items( 'group' ) : array() as $key => $group ) {
			// A trashed database copy still hides the file, so trashing doesn't bring the file version back.
			if ( ! isset( $this->loaded[ $key ] ) && ! in_array( $key, $stored['trashed'], true ) ) {
				$this->loaded[ $key ] = new Group( array_merge( $group, array( 'id' => 0 ) ) );
			}
		}

		/**
		 * Filters the loaded Field Groups. Add-ons can append groups defined in code.
		 *
		 * @param array<string, Group> $groups Groups keyed by group key.
		 */
		$filtered = apply_filters( 'cfmb/groups/loaded', $this->loaded ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->loaded = array();
		foreach ( (array) $filtered as $group ) {
			if ( $group instanceof Group ) {
				$this->loaded[ $group->key() ] = $group;
			}
		}

		$this->loaded = ( new Preset_Resolver( $this->loaded ) )->resolve_all();

		return array_values( $this->loaded );
	}

	/**
	 * Active groups only.
	 *
	 * @return Group[]
	 */
	public function active(): array {
		return array_values( array_filter( $this->all(), static fn( Group $group ) => $group->active() ) );
	}

	/**
	 * Groups in the trash.
	 *
	 * @return Group[]
	 */
	public function trashed(): array {
		$posts = get_posts( $this->query_args( array( 'trash' ) ) );

		return array_map( fn( WP_Post $post ) => new Group( $this->hydrate( $post ) ), $posts );
	}

	/**
	 * Groups that insert the given group as a preset, at any depth.
	 *
	 * @param string $key Source group key.
	 * @return Group[]
	 */
	public function preset_users( string $key ): array {
		return array_values(
			array_filter(
				$this->all(),
				static fn( Group $group ) => $group->key() !== $key && Preset_Resolver::uses_source( $group->stored_fields(), $key )
			)
		);
	}

	/**
	 * Find a group by key, including trashed groups.
	 *
	 * @param string $key Group key.
	 * @return Group|null
	 */
	public function find( string $key ): ?Group {
		foreach ( $this->all() as $group ) {
			if ( $group->key() === $key ) {
				return $group;
			}
		}

		$post = $this->post_for_key( $key, array( 'trash' ) );

		if ( ! $post ) {
			return null;
		}

		$group = new Group( $this->hydrate( $post ) );

		return ( new Preset_Resolver( array_merge( $this->loaded ?? array(), array( $key => $group ) ) ) )->resolve( $group );
	}

	/**
	 * Create or update a group from its sanitized array form.
	 *
	 * @param array<string, mixed> $group Sanitized group (see Group_Sanitizer).
	 * @return Group|WP_Error
	 */
	public function save( array $group ) {
		$group = array_merge( Group::defaults(), $group );
		$post  = $this->post_for_key( (string) $group['key'], array( 'publish', 'draft', 'trash' ) );

		$body = array(
			'version'  => Group::FORMAT_VERSION,
			'fields'   => array_values( (array) $group['fields'] ),
			'location' => array_values( (array) $group['location'] ),
			'settings' => (array) $group['settings'],
		);

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => (string) $group['title'],
			'post_name'    => (string) $group['key'],
			'post_status'  => $group['active'] ? 'publish' : 'draft',
			'menu_order'   => (int) $group['order'],
			'post_content' => wp_slash( (string) wp_json_encode( $body ) ),
		);

		if ( $post ) {
			$postarr['ID'] = $post->ID;

			if ( 'trash' === $post->post_status ) {
				// WordPress suffixes trashed slugs; keep that so restore still works.
				$postarr['post_status'] = 'trash';
				$postarr['post_name']   = $post->post_name;
			}
		}

		$post_id = $this->write_post( $postarr );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->flush();

		$saved = get_post( $post_id );

		if ( ! $saved instanceof WP_Post ) {
			return new WP_Error( 'cfmb_group_not_saved', __( 'The field group could not be saved. Please try again.', 'custom-fields-meta-box' ) );
		}

		$stored = new Group( $this->hydrate( $saved ) );

		/** This action is documented in src/Content/Content_Repository.php */
		do_action( 'cfmb/schema/saved', 'group', $stored->to_array(), (int) $post_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $stored;
	}

	/**
	 * Storage post ID for a key, 0 when not stored.
	 *
	 * @param string $key Group key.
	 * @return int
	 */
	public function post_id( string $key ): int {
		$post = $this->post_for_key( $key, array( 'publish', 'draft', 'trash' ) );

		return $post ? (int) $post->ID : 0;
	}

	/**
	 * Move a group to the trash. Values already saved on posts are kept.
	 *
	 * @param string $key Group key.
	 * @return bool
	 */
	public function trash( string $key ): bool {
		$post = $this->post_for_key( $key, array( 'publish', 'draft' ) );

		if ( ! $post ) {
			return false;
		}

		$done = (bool) wp_trash_post( $post->ID );
		$this->flush();

		return $done;
	}

	/**
	 * Restore a trashed group with its previous active state.
	 *
	 * @param string $key Group key.
	 * @return bool
	 */
	public function restore( string $key ): bool {
		$post = $this->post_for_key( $key, array( 'trash' ) );

		if ( ! $post ) {
			return false;
		}

		$done = (bool) wp_untrash_post( $post->ID );
		$this->flush();

		return $done;
	}

	/**
	 * Delete a group for good. Values saved on posts are kept.
	 *
	 * @param string $key Group key.
	 * @return bool
	 */
	public function delete( string $key ): bool {
		$post = $this->post_for_key( $key, array( 'publish', 'draft', 'trash' ) );

		if ( ! $post ) {
			return false;
		}

		$done = (bool) wp_delete_post( $post->ID, true );
		$this->flush();

		if ( $done ) {
			/** This action is documented in src/Content/Content_Repository.php */
			do_action( 'cfmb/schema/deleted', 'group', $key ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		}

		return $done;
	}

	/**
	 * Copy a group with new group and field keys. The copy starts inactive
	 * so it never shows twice on edit screens by surprise.
	 *
	 * @param string $key Group key.
	 * @return Group|WP_Error
	 */
	public function duplicate( string $key ) {
		$source = $this->find( $key );

		if ( ! $source ) {
			return new WP_Error( 'cfmb_group_missing', __( 'That field group no longer exists.', 'custom-fields-meta-box' ) );
		}

		$copy        = $source->to_array();
		$copy['key'] = Key_Generator::group();
		$copy['id']  = 0;

		$copy['title'] = sprintf(
			/* translators: %s: original field group title */
			__( '%s (copy)', 'custom-fields-meta-box' ),
			$source->title()
		);
		$copy['active'] = false;
		$copy['fields'] = self::rekey_fields( $copy['fields'] );

		return $this->save( $copy );
	}

	/**
	 * Give fields and layouts new keys at every depth. Conditions point at
	 * siblings, so they follow the new keys within each list.
	 *
	 * @param array<int, array<string, mixed>> $fields Field definitions.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rekey_fields( array $fields ): array {
		$key_map = array();

		foreach ( $fields as $index => $field ) {
			$new_key                        = Key_Generator::field();
			$key_map[ $field['key'] ?? '' ] = $new_key;
			$fields[ $index ]['key']        = $new_key;

			if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$fields[ $index ]['fields'] = self::rekey_fields( $field['fields'] );
			}

			foreach ( (array) ( $field['layouts'] ?? array() ) as $l => $layout ) {
				$fields[ $index ]['layouts'][ $l ]['key']    = Key_Generator::layout();
				$fields[ $index ]['layouts'][ $l ]['fields'] = self::rekey_fields( (array) ( $layout['fields'] ?? array() ) );
			}
		}

		foreach ( $fields as $index => $field ) {
			foreach ( (array) ( $field['conditions'] ?? array() ) as $or => $rules ) {
				foreach ( (array) $rules as $and => $rule ) {
					$old = $rule['field'] ?? '';
					if ( isset( $key_map[ $old ] ) ) {
						$fields[ $index ]['conditions'][ $or ][ $and ]['field'] = $key_map[ $old ];
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Keep a group's active/inactive state when it leaves the trash.
	 * WordPress restores to draft by default.
	 *
	 * @param string $new_status      Status WordPress picked.
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status Status before trashing.
	 * @return string
	 */
	public function restore_previous_status( $new_status, $post_id, $previous_status ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $new_status;
		}

		return in_array( $previous_status, array( 'publish', 'draft' ), true ) ? $previous_status : $new_status;
	}

	/**
	 * Flush caches when one of our posts changes outside this class.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function maybe_flush( $post_id, $post ): void {
		if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
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
	 * Load group arrays, and the keys of trashed groups, from the object cache or one query.
	 *
	 * @return array{groups: array<int, array<string, mixed>>, trashed: string[]}
	 */
	private function load_arrays(): array {
		$cache_key = 'groups:' . wp_cache_get_last_changed( self::CACHE_GROUP );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) && isset( $cached['groups'], $cached['trashed'] ) ) {
			return $cached;
		}

		$stored = array(
			'groups'  => array(),
			'trashed' => array(),
		);

		foreach ( get_posts( $this->query_args( array( 'publish', 'draft', 'trash' ) ) ) as $post ) {
			if ( 'trash' === $post->post_status ) {
				$stored['trashed'][] = (string) preg_replace( '/__trashed$/', '', $post->post_name );
				continue;
			}

			$stored['groups'][] = $this->hydrate( $post );
		}

		wp_cache_set( $cache_key, $stored, self::CACHE_GROUP );

		return $stored;
	}

	/**
	 * Turn a stored post into a group array.
	 *
	 * @param WP_Post $post Group post.
	 * @return array<string, mixed>
	 */
	private function hydrate( WP_Post $post ): array {
		$body = json_decode( $post->post_content, true );
		$body = is_array( $body ) ? $body : array();

		$status = $post->post_status;
		if ( 'trash' === $status ) {
			$status = (string) get_post_meta( $post->ID, '_wp_trash_meta_status', true );
		}

		return array(
			'key'      => (string) preg_replace( '/__trashed$/', '', $post->post_name ),
			'title'    => $post->post_title,
			'id'       => $post->ID,
			'active'   => 'publish' === $status,
			'order'    => (int) $post->menu_order,
			'fields'   => isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : array(),
			'location' => isset( $body['location'] ) && is_array( $body['location'] ) ? $body['location'] : array(),
			'settings' => isset( $body['settings'] ) && is_array( $body['settings'] ) ? $body['settings'] : array(),
		);
	}

	/**
	 * Find the stored post for a key.
	 *
	 * @param string   $key      Group key.
	 * @param string[] $statuses Statuses to search.
	 * @return WP_Post|null
	 */
	private function post_for_key( string $key, array $statuses ): ?WP_Post {
		if ( ! Key_Generator::is_valid( $key, Key_Generator::GROUP_PREFIX ) ) {
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
	 * @param string[] $statuses Post statuses.
	 * @return array<string, mixed>
	 */
	private function query_args( array $statuses ): array {
		return array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => $statuses,
			'numberposts'            => -1,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);
	}

	/**
	 * Insert or update the post without kses touching the JSON body.
	 *
	 * Users without unfiltered_html would otherwise have the JSON run through
	 * the post HTML filter, which breaks it. Every value inside was already
	 * sanitized field by field in Group_Sanitizer.
	 *
	 * @param array<string, mixed> $postarr Post data.
	 * @return int|WP_Error
	 */
	private function write_post( array $postarr ) {
		$priority = has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( false !== $priority ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses', $priority );
		}

		$post_id = isset( $postarr['ID'] )
			? wp_update_post( $postarr, true )
			: wp_insert_post( $postarr, true );

		if ( false !== $priority ) {
			add_filter( 'content_save_pre', 'wp_filter_post_kses', $priority );
		}

		return $post_id;
	}
}
