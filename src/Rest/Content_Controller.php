<?php
/**
 * REST routes for post type, taxonomy, options page and Connection definitions.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Connections\Connection_Repository;
use CFMB\Connections\Connection_Store;
use CFMB\Content\Content_Mover;
use CFMB\Content\Content_Repository;
use CFMB\Content\Options_Page_Repository;
use CFMB\Content\Post_Type_Repository;
use CFMB\Content\Taxonomy_Repository;
use CFMB\Sync\Sync_Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/content/{post-types|taxonomies|options-pages|connections}: list, create,
 * read, update, trash, restore, duplicate. Used by the builder.
 */
class Content_Controller {

	/**
	 * URL segment per kind.
	 */
	private const SEGMENTS = array(
		'post-types'    => 'post_type',
		'taxonomies'    => 'taxonomy',
		'options-pages' => 'options_page',
		'connections'   => 'connection',
	);

	/**
	 * Repositories by kind.
	 *
	 * @var array<string, Content_Repository>
	 */
	private array $repositories;

	/**
	 * Moves content on renames.
	 *
	 * @var Content_Mover
	 */
	private Content_Mover $mover;

	/**
	 * Schema Sync, for write notes.
	 *
	 * @var Sync_Manager
	 */
	private Sync_Manager $sync;

	/**
	 * Connection links, moved when a connection ID changes.
	 *
	 * @var Connection_Store|null
	 */
	private ?Connection_Store $links;

	/**
	 * Constructor.
	 *
	 * @param Post_Type_Repository       $post_types    Post types.
	 * @param Taxonomy_Repository        $taxonomies    Taxonomies.
	 * @param Options_Page_Repository    $options_pages Options pages.
	 * @param Content_Mover              $mover         Moves content on renames.
	 * @param Sync_Manager               $sync          Schema Sync.
	 * @param Connection_Repository|null $connections   Connections.
	 * @param Connection_Store|null      $links         Connection links.
	 */
	public function __construct( Post_Type_Repository $post_types, Taxonomy_Repository $taxonomies, Options_Page_Repository $options_pages, Content_Mover $mover, Sync_Manager $sync, ?Connection_Repository $connections = null, ?Connection_Store $links = null ) {
		$this->repositories = array(
			'post_type'    => $post_types,
			'taxonomy'     => $taxonomies,
			'options_page' => $options_pages,
		);

		if ( $connections ) {
			$this->repositories['connection'] = $connections;
		}

		$this->mover = $mover;
		$this->sync  = $sync;
		$this->links = $links;
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
		$base = '/content/(?P<type>post-types|taxonomies|options-pages|connections)';
		$item = $base . '/(?P<key>(cpt|tax|opt|con)_[a-z0-9]{6,32})';
		$can  = array( $this, 'can_manage' );

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $can,
					'args'                => array(
						'status' => array(
							'type'    => 'string',
							'enum'    => array( 'any', 'trash' ),
							'default' => 'any',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $can,
				),
			)
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			$item,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $can,
					'args'                => array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			$item . '/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore_item' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			$item . '/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * Same capability as Field Groups.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( cfmb_admin_capability() );
	}

	/**
	 * List definitions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$repo  = $this->repository( $request );
		$items = 'trash' === $request['status'] ? $repo->trashed() : $repo->all();

		return rest_ensure_response( array_map( array( $this, 'prepare' ), $items ) );
	}

	/**
	 * Create a definition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$repo  = $this->repository( $request );
		$input = (array) $request->get_json_params();
		$clean = $repo->schema()->sanitize( $input, null, $repo->names_taken() );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( $repo->find( (string) $clean['key'] ) ) {
			return new WP_Error( 'cfmb_content_exists', __( 'Something with this key already exists.', 'cfmb' ), array( 'status' => 409 ) );
		}

		$saved = $repo->save( $clean );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$response = rest_ensure_response( $this->prepare( $saved ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Read a definition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$item = $this->repository( $request )->find( (string) $request['key'] );

		return $item ? rest_ensure_response( $this->prepare( $item ) ) : $this->not_found();
	}

	/**
	 * Update a definition. With move_content, posts or terms follow a changed key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$repo     = $this->repository( $request );
		$existing = $repo->find( (string) $request['key'] );

		if ( ! $existing ) {
			return $this->not_found();
		}

		if ( 'db' !== $existing['source'] ) {
			return new WP_Error( 'cfmb_content_read_only', __( 'This is defined in code or a sync file. Import it under CFMB > Sync to edit it here.', 'cfmb' ), array( 'status' => 409 ) );
		}

		$input = (array) $request->get_json_params();
		$clean = $repo->schema()->sanitize( $input, $existing, $repo->names_taken( (string) $existing['key'] ) );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$saved = $repo->save( $clean );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$moved = 0;
		$from  = (string) $existing['name'];
		$to    = (string) $saved['name'];

		if ( $from !== $to ) {
			$kind = $repo->schema()->kind();

			$move = ! empty( $input['move_content'] );

			if ( 'connection' === $kind ) {
				$moved = $move && $this->links ? $this->links->rename( $from, $to ) : 0;
			} elseif ( 'options_page' === $kind ) {
				$this->mover->rename_options_page( $from, $to );
			} elseif ( 'post_type' === $kind ) {
				$moved = $this->mover->move_post_type( $from, $to, $move );
			} else {
				$moved = $this->mover->move_taxonomy( $from, $to, $move );
			}
		}

		$data          = $this->prepare( $saved );
		$data['moved'] = $moved;

		return rest_ensure_response( $data );
	}

	/**
	 * Trash or delete a definition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$repo = $this->repository( $request );
		$key  = (string) $request['key'];
		$done = $request['force'] ? $repo->delete( $key ) : $repo->trash( $key );

		if ( ! $done ) {
			return $this->not_found();
		}

		return rest_ensure_response(
			array(
				'key'     => $key,
				'deleted' => (bool) $request['force'],
				'trashed' => ! $request['force'],
			)
		);
	}

	/**
	 * Restore a trashed definition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( WP_REST_Request $request ) {
		$repo = $this->repository( $request );
		$key  = (string) $request['key'];

		if ( ! $repo->restore( $key ) ) {
			return $this->not_found();
		}

		$item = $repo->find( $key );

		return $item ? rest_ensure_response( $this->prepare( $item ) ) : $this->not_found();
	}

	/**
	 * Copy a definition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_item( WP_REST_Request $request ) {
		$copy = $this->repository( $request )->duplicate( (string) $request['key'] );

		if ( is_wp_error( $copy ) ) {
			return $copy;
		}

		$response = rest_ensure_response( $this->prepare( $copy ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Response shape: the definition plus where it comes from and any sync note.
	 *
	 * @param array<string, mixed> $item Definition.
	 * @return array<string, mixed>
	 */
	public function prepare( array $item ): array {
		$notes = $this->sync->write_notes();

		return array(
			'key'      => (string) $item['key'],
			'name'     => (string) $item['name'],
			'title'    => (string) $item['title'],
			'active'   => (bool) $item['active'],
			'settings' => (object) $item['settings'],
			'id'       => (int) ( $item['id'] ?? 0 ),
			'source'   => (string) ( $item['source'] ?? 'db' ),
			'syncNote' => (string) ( $notes[ $item['key'] ] ?? '' ),
		);
	}

	/**
	 * Repository for the request's segment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return Content_Repository
	 */
	private function repository( WP_REST_Request $request ): Content_Repository {
		return $this->repositories[ self::SEGMENTS[ (string) $request['type'] ] ?? 'post_type' ];
	}

	/**
	 * Not found error.
	 *
	 * @return WP_Error
	 */
	private function not_found(): WP_Error {
		return new WP_Error( 'cfmb_content_missing', __( 'That item no longer exists. It may have been deleted in another tab.', 'cfmb' ), array( 'status' => 404 ) );
	}
}
