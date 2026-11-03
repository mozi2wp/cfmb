<?php
/**
 * REST route for Connection box search.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Connections\Connection;
use CFMB\Connections\Connection_Panels;
use CFMB\Connections\Connection_Registry;
use CFMB\Connections\Connection_Saver;
use CFMB\Storage\Object_Ref;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/connections/{connection}/search: items the box on one side may
 * link to. Kinds and types come from the connection on the server. The
 * user must be able to edit the object the box is on, and only sees items
 * they may pick (see Connection_Saver::can_pick()).
 */
class Connections_Controller {

	private const PER_PAGE = 20;

	/**
	 * Active connections.
	 *
	 * @var Connection_Registry
	 */
	private Connection_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Connection_Registry $registry Active connections.
	 */
	public function __construct( Connection_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/connections/(?P<connection>[a-z0-9_-]{1,64})/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_search' ),
				'args'                => array(
					'side'   => array(
						'type'     => 'string',
						'enum'     => array( 'from', 'to' ),
						'required' => true,
					),
					'object' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'   => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * The user must be able to edit the object the box is on.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_search( WP_REST_Request $request ) {
		$connection = $this->registry->find( (string) $request['connection'] );
		$side       = (string) $request['side'];

		if ( ! $connection || ! is_user_logged_in() ) {
			return new WP_Error( 'cfmb_connection_missing', __( 'That connection no longer exists. Reload the page.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
		}

		$ref = $connection->ref( $side, (int) $request['object'] );

		if ( ! $connection->accepts( $side, $ref ) ) {
			return new WP_Error( 'cfmb_connection_object', __( 'This item can\'t use that connection.', 'custom-fields-meta-box' ), array( 'status' => 400 ) );
		}

		switch ( $ref->kind() ) {
			case Object_Ref::POST:
				return current_user_can( 'edit_post', $ref->id() );
			case Object_Ref::TERM:
				return current_user_can( 'edit_term', $ref->id() );
			case Object_Ref::USER:
				return current_user_can( 'edit_user', $ref->id() );
		}

		return false;
	}

	/**
	 * Search the other side.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response {
		$connection = $this->registry->find( (string) $request['connection'] );
		$other      = Connection::other( (string) $request['side'] );
		$search     = sanitize_text_field( (string) $request['search'] );
		$page       = max( 1, (int) $request['page'] );

		if ( ! $connection ) {
			return rest_ensure_response(
				array(
					'items' => array(),
					'more'  => false,
				)
			);
		}

		switch ( $connection->kind( $other ) ) {
			case 'term':
				$found = $this->terms( $connection->types( $other ), $search, $page );
				break;
			case 'user':
				$found = $this->users( $connection->types( $other ), $search, $page );
				break;
			default:
				$found = $this->posts( $connection->types( $other ), $search, $page );
		}

		$items = array();
		foreach ( $found['ids'] as $id ) {
			$item = Connection_Panels::describe( $connection->kind( $other ), (int) $id );

			if ( $item && Connection_Saver::can_pick( $connection->kind( $other ), (int) $id ) ) {
				$items[] = $item;
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'more'  => $found['more'],
			)
		);
	}

	/**
	 * Post IDs.
	 *
	 * @param string[] $types  Allowed post types; empty for all shown in the admin.
	 * @param string   $search Search text.
	 * @param int      $page   Page.
	 * @return array{ids: int[], more: bool}
	 */
	private function posts( array $types, string $search, int $page ): array {
		$types = $types ? $types : array_values( array_diff( get_post_types( array( 'show_ui' => true ) ), array( 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ) ) );
		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ),
				's'                      => $search,
				'posts_per_page'         => self::PER_PAGE,
				'paged'                  => $page,
				'orderby'                => '' !== $search ? 'relevance' : 'title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'perm'                   => 'readable',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array(
			'ids'  => array_map( 'intval', $query->posts ),
			'more' => $page < (int) $query->max_num_pages,
		);
	}

	/**
	 * Term IDs.
	 *
	 * @param string[] $types  Allowed taxonomies; empty for all shown in the admin.
	 * @param string   $search Search text.
	 * @param int      $page   Page.
	 * @return array{ids: int[], more: bool}
	 */
	private function terms( array $types, string $search, int $page ): array {
		$types = array_values( array_filter( $types ? $types : get_taxonomies( array( 'show_ui' => true ) ), 'taxonomy_exists' ) );

		if ( ! $types ) {
			return array(
				'ids'  => array(),
				'more' => false,
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $types,
				'search'     => $search,
				'hide_empty' => false,
				'number'     => self::PER_PAGE + 1,
				'offset'     => ( $page - 1 ) * self::PER_PAGE,
				'orderby'    => 'name',
				'fields'     => 'ids',
			)
		);

		$ids = is_array( $terms ) ? array_map( 'intval', $terms ) : array();

		return array(
			'ids'  => array_slice( $ids, 0, self::PER_PAGE ),
			'more' => count( $ids ) > self::PER_PAGE,
		);
	}

	/**
	 * User IDs. People who can't list users only find themselves.
	 *
	 * @param string[] $roles  Allowed roles; empty for all.
	 * @param string   $search Search text.
	 * @param int      $page   Page.
	 * @return array{ids: int[], more: bool}
	 */
	private function users( array $roles, string $search, int $page ): array {
		$args = array(
			'number'      => self::PER_PAGE,
			'paged'       => $page,
			'orderby'     => 'display_name',
			'fields'      => 'ID',
			'count_total' => true,
		);

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = current_user_can( 'list_users' ) ? array( 'user_login', 'user_nicename', 'display_name', 'user_email' ) : array( 'user_nicename', 'display_name' );
		}

		if ( $roles ) {
			$args['role__in'] = $roles;
		}

		if ( ! current_user_can( 'list_users' ) ) {
			$args['include'] = array( get_current_user_id() );
		}

		$query = new WP_User_Query( $args );
		$ids   = array_map( 'intval', (array) $query->get_results() );

		return array(
			'ids'  => $ids,
			'more' => $page * self::PER_PAGE < (int) $query->get_total(),
		);
	}
}
