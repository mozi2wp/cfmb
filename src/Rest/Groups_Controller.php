<?php
/**
 * REST routes for Field Groups.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Sync\Sync_Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/groups: list, create, read, update, trash, restore, duplicate.
 */
class Groups_Controller {

	public const REST_NAMESPACE = 'cfmb/v1';

	/**
	 * Group storage.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Input sanitizer.
	 *
	 * @var Group_Sanitizer
	 */
	private Group_Sanitizer $sanitizer;

	/**
	 * Schema Sync, for notes about files after a save. Optional.
	 *
	 * @var Sync_Manager|null
	 */
	private ?Sync_Manager $sync;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups    Group storage.
	 * @param Group_Sanitizer  $sanitizer Input sanitizer.
	 */
	public function __construct( Group_Repository $groups, Group_Sanitizer $sanitizer, ?Sync_Manager $sync = null ) {
		$this->groups    = $groups;
		$this->sanitizer = $sanitizer;
		$this->sync      = $sync;
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
		$key_arg = array(
			'key' => array(
				'type'    => 'string',
				'pattern' => '^grp_[a-z0-9]{6,32}$',
			),
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/groups',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_groups' ),
					'permission_callback' => array( $this, 'can_manage' ),
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
					'callback'            => array( $this, 'create_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/groups/(?P<key>grp_[a-z0-9]{6,32})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => $key_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => $key_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array_merge(
						$key_arg,
						array(
							'force' => array(
								'type'    => 'boolean',
								'default' => false,
							),
						)
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/groups/(?P<key>grp_[a-z0-9]{6,32})/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore_group' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => $key_arg,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/groups/(?P<key>grp_[a-z0-9]{6,32})/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_group' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => $key_arg,
			)
		);
	}

	/**
	 * Only users with the CFMB admin capability.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( cfmb_admin_capability() );
	}

	/**
	 * List groups.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_groups( WP_REST_Request $request ): WP_REST_Response {
		$groups = 'trash' === $request['status'] ? $this->groups->trashed() : $this->stored_groups();

		return rest_ensure_response( array_map( static fn( Group $group ) => $group->to_array(), $groups ) );
	}

	/**
	 * Create a group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_group( WP_REST_Request $request ) {
		$clean = $this->sanitizer->sanitize( (array) $request->get_json_params() );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( $this->groups->find( $clean['key'] ) ) {
			return new WP_Error( 'cfmb_group_exists', __( 'A field group with this key already exists.', 'cfmb' ), array( 'status' => 409 ) );
		}

		$group = $this->groups->save( $clean );

		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$response = rest_ensure_response( $this->prepare( $group ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Read a group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_group( WP_REST_Request $request ) {
		$group = $this->groups->find( (string) $request['key'] );

		return $group ? rest_ensure_response( $this->prepare( $group ) ) : $this->not_found();
	}

	/**
	 * Update a group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_group( WP_REST_Request $request ) {
		$existing = $this->groups->find( (string) $request['key'] );

		if ( ! $existing ) {
			return $this->not_found();
		}

		if ( 'db' !== $existing->source() ) {
			return $this->read_only();
		}

		$clean = $this->sanitizer->sanitize( (array) $request->get_json_params(), $existing );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$group = $this->groups->save( $clean );

		return is_wp_error( $group ) ? $group : rest_ensure_response( $this->prepare( $group ) );
	}

	/**
	 * Trash or delete a group. Field values on content are never touched.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_group( WP_REST_Request $request ) {
		$key      = (string) $request['key'];
		$existing = $this->groups->find( $key );

		if ( $existing && 'db' !== $existing->source() ) {
			return $this->read_only();
		}

		$done = $request['force'] ? $this->groups->delete( $key ) : $this->groups->trash( $key );

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
	 * Restore a trashed group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_group( WP_REST_Request $request ) {
		$key = (string) $request['key'];

		if ( ! $this->groups->restore( $key ) ) {
			return $this->not_found();
		}

		$group = $this->groups->find( $key );

		return $group ? rest_ensure_response( $group->to_array() ) : $this->not_found();
	}

	/**
	 * Duplicate a group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_group( WP_REST_Request $request ) {
		$group = $this->groups->duplicate( (string) $request['key'] );

		if ( is_wp_error( $group ) ) {
			return $group;
		}

		$response = rest_ensure_response( $group->to_array() );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * A group for the builder, with the groups that insert it as a preset,
	 * so renaming its fields can warn about them.
	 *
	 * @param Group $group Group.
	 * @return array<string, mixed>
	 */
	private function prepare( Group $group ): array {
		$data           = $group->to_array();
		$data['usedBy'] = array_map(
			static fn( Group $user ) => array(
				'key'   => $user->key(),
				'title' => $user->title(),
			),
			$this->groups->preset_users( $group->key() )
		);
		$notes            = $this->sync ? $this->sync->write_notes() : array();
		$data['syncNote'] = (string) ( $notes[ $group->key() ] ?? '' );

		return $data;
	}

	/**
	 * Error for groups defined in code or a sync file.
	 *
	 * @return WP_Error
	 */
	private function read_only(): WP_Error {
		return new WP_Error( 'cfmb_group_read_only', __( 'This field group is defined in code or a sync file. Import it under CFMB > Sync to edit it here.', 'cfmb' ), array( 'status' => 409 ) );
	}

	/**
	 * Every group. Groups from code or sync files carry their source and
	 * are read-only in the builder.
	 *
	 * @return Group[]
	 */
	private function stored_groups(): array {
		return $this->groups->all();
	}

	/**
	 * 404 error.
	 *
	 * @return WP_Error
	 */
	private function not_found(): WP_Error {
		return new WP_Error( 'cfmb_group_missing', __( 'That field group no longer exists. It may have been deleted in another tab.', 'cfmb' ), array( 'status' => 404 ) );
	}
}
