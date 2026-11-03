<?php
/**
 * Reading and changing links, with visibility rules.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Display\Visibility;
use CFMB\Storage\Object_Ref;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Everything outside the admin boxes goes through here: the template API,
 * Field Loops, WP_Query and the REST API. Front-end reads only list items
 * the visitor may see.
 *
 * WP_Query takes a `cfmb_connected` argument:
 *
 *     new WP_Query( array(
 *         'post_type'      => 'book',
 *         'cfmb_connected' => array( 'connection' => 'book_authors', 'object' => 'user_3' ),
 *     ) );
 *
 * `object` is any form cfmb_get_field() accepts; `side` (from or to) is
 * only needed when the object could be on both sides. Results keep the
 * connection's order unless `orderby` is set.
 */
class Connections {

	/**
	 * Active connections.
	 *
	 * @var Connection_Registry
	 */
	private Connection_Registry $registry;

	/**
	 * Links.
	 *
	 * @var Connection_Store
	 */
	private Connection_Store $store;

	/**
	 * Visibility rules shared with Dynamic Data.
	 *
	 * @var Visibility
	 */
	private Visibility $visibility;

	/**
	 * Constructor.
	 *
	 * @param Connection_Registry $registry   Active connections.
	 * @param Connection_Store    $store      Links.
	 * @param Visibility          $visibility Visibility rules.
	 */
	public function __construct( Connection_Registry $registry, Connection_Store $store, Visibility $visibility ) {
		$this->registry   = $registry;
		$this->store      = $store;
		$this->visibility = $visibility;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	/**
	 * Active connections.
	 *
	 * @return Connection_Registry
	 */
	public function registry(): Connection_Registry {
		return $this->registry;
	}

	/**
	 * Links.
	 *
	 * @return Connection_Store
	 */
	public function store(): Connection_Store {
		return $this->store;
	}

	/**
	 * IDs linked to an object.
	 *
	 * @param string               $name Connection ID.
	 * @param Object_Ref           $ref  Object.
	 * @param array<string, mixed> $args side (from, to or empty for any), visible (only items the visitor may see, default true).
	 * @return int[]
	 */
	public function ids( string $name, Object_Ref $ref, array $args = array() ): array {
		$connection = $this->registry->find( $name );

		if ( ! $connection ) {
			return array();
		}

		$sides = $connection->sides_for( $ref );
		$side  = (string) ( $args['side'] ?? '' );

		if ( '' !== $side ) {
			$sides = in_array( $side, $sides, true ) ? array( $side ) : array();
		}

		$ids = array();

		foreach ( $sides as $from_side ) {
			$other = Connection::other( $from_side );

			foreach ( $this->store->ids( $name, $from_side, $ref->id() ) as $id ) {
				if ( ! in_array( $id, $ids, true ) && ( false === ( $args['visible'] ?? true ) || $this->visibility->can_read( $connection->ref( $other, $id ), Visibility::VIA_CONNECTION ) ) ) {
					$ids[] = $id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Objects linked to an object: WP_Post, WP_Term or WP_User.
	 *
	 * @param string               $name Connection ID.
	 * @param Object_Ref           $ref  Object.
	 * @param array<string, mixed> $args See ids().
	 * @return array<int, WP_Post|WP_Term|WP_User>
	 */
	public function objects( string $name, Object_Ref $ref, array $args = array() ): array {
		$connection = $this->registry->find( $name );

		if ( ! $connection ) {
			return array();
		}

		$kind = $this->other_kind( $connection, $ref, (string) ( $args['side'] ?? '' ) );
		$ids  = $this->ids( $name, $ref, $args );

		if ( 'post' === $kind && $ids ) {
			_prime_post_caches( $ids, false, true );
		}

		$objects = array();

		foreach ( $ids as $id ) {
			$object = 'post' === $kind ? get_post( $id ) : ( 'term' === $kind ? get_term( $id ) : get_userdata( $id ) );

			if ( $object instanceof WP_Post || $object instanceof WP_Term || $object instanceof WP_User ) {
				$objects[] = $object;
			}
		}

		return $objects;
	}

	/**
	 * Kind of the items linked to an object.
	 *
	 * @param Connection $connection Connection.
	 * @param Object_Ref $ref        Object.
	 * @param string     $side       Side the object is on, or empty.
	 * @return string post, term, user, or empty when the object is on neither side.
	 */
	public function other_kind( Connection $connection, Object_Ref $ref, string $side = '' ): string {
		$sides = $connection->sides_for( $ref );

		if ( '' !== $side ) {
			$sides = in_array( $side, $sides, true ) ? array( $side ) : array();
		}

		return $sides ? $connection->kind( Connection::other( $sides[0] ) ) : '';
	}

	/**
	 * Narrow a WP_Query to posts linked to an object.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function filter_query( $query ): void {
		if ( ! $query instanceof WP_Query ) {
			return;
		}

		$args = $query->get( 'cfmb_connected' );

		if ( ! is_array( $args ) || empty( $args['connection'] ) ) {
			return;
		}

		$ref = Object_Ref::from( $args['object'] ?? null );
		$ids = $ref ? $this->ids( sanitize_key( (string) $args['connection'] ), $ref, array( 'side' => (string) ( $args['side'] ?? '' ) ) ) : array();

		$query->set( 'post__in', $ids ? $ids : array( 0 ) );

		if ( '' === (string) $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'post__in' );
		}
	}

	/**
	 * Add a read-only cfmb_connections attribute where a connection opts in.
	 *
	 * @return void
	 */
	public function register_rest_fields(): void {
		$targets = array();

		foreach ( $this->registry->all() as $connection ) {
			if ( ! $connection instanceof Connection || ! $connection->in_rest() ) {
				continue;
			}

			foreach ( array( 'from', 'to' ) as $side ) {
				foreach ( $this->rest_objects( $connection, $side ) as $object ) {
					$targets[ $object ] = true;
				}
			}
		}

		if ( ! $targets ) {
			return;
		}

		register_rest_field(
			array_keys( $targets ),
			'cfmb_connections',
			array(
				'get_callback' => array( $this, 'rest_value' ),
				'schema'       => array(
					'description' => __( 'IDs of connected items, by connection ID.', 'custom-fields-meta-box' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * Value of the cfmb_connections attribute.
	 *
	 * @param array<string, mixed> $item    Prepared item.
	 * @param string               $field   Field name.
	 * @param WP_REST_Request      $request Request.
	 * @param string               $type    Object type: a post type, a taxonomy or "user".
	 * @return array<string, int[]>
	 */
	public function rest_value( $item, $field = '', $request = null, $type = '' ): array {
		$id = (int) ( $item['id'] ?? 0 );

		if ( 'user' === $type ) {
			$ref = new Object_Ref( Object_Ref::USER, $id );
		} elseif ( taxonomy_exists( (string) $type ) ) {
			$ref = new Object_Ref( Object_Ref::TERM, $id );
		} else {
			$ref = new Object_Ref( Object_Ref::POST, $id );
		}

		if ( ! $this->visibility->can_read( $ref ) ) {
			return array();
		}

		$value = array();

		foreach ( $this->registry->for_object( $ref ) as $entry ) {
			$connection = $entry['connection'];

			if ( $connection->in_rest() && ! isset( $value[ $connection->name() ] ) ) {
				$value[ $connection->name() ] = $this->ids( $connection->name(), $ref );
			}
		}

		return $value;
	}

	/**
	 * REST object types for a side: post types, taxonomies or "user".
	 *
	 * @param Connection $connection Connection.
	 * @param string     $side       Side.
	 * @return string[]
	 */
	private function rest_objects( Connection $connection, string $side ): array {
		$types = $connection->types( $side );

		switch ( $connection->kind( $side ) ) {
			case 'user':
				return array( 'user' );

			case 'term':
				return $types ? $types : array_values( get_taxonomies( array( 'show_in_rest' => true ) ) );
		}

		return $types ? $types : array_values( get_post_types( array( 'show_in_rest' => true ) ) );
	}
}
