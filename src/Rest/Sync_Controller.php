<?php
/**
 * REST routes for Schema Sync.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Sync\Php_Exporter;
use CFMB\Sync\Sync_Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/sync: state of every definition, diff, push, pull, merge and export.
 */
class Sync_Controller {

	/**
	 * Schema Sync.
	 *
	 * @var Sync_Manager
	 */
	private Sync_Manager $sync;

	/**
	 * Constructor.
	 *
	 * @param Sync_Manager $sync Schema Sync.
	 */
	public function __construct( Sync_Manager $sync ) {
		$this->sync = $sync;
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
		$can   = array( $this, 'can_manage' );
		$items = array(
			'items' => array(
				'type'     => 'array',
				'required' => true,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						'kind' => array(
							'type' => 'string',
							'enum' => Sync_Manager::kinds(),
						),
						'key'  => array(
							'type'    => 'string',
							'pattern' => '^(grp|cpt|tax|opt)_[a-z0-9]{6,32}$',
						),
					),
				),
			),
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/sync',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/sync/(?P<kind>group|post_type|taxonomy|options_page|connection)/(?P<key>(grp|cpt|tax|opt|con)_[a-z0-9]{6,32})/diff',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'diff' ),
				'permission_callback' => $can,
			)
		);

		foreach ( array( 'push', 'pull' ) as $action ) {
			register_rest_route(
				Groups_Controller::REST_NAMESPACE,
				'/sync/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action ),
					'permission_callback' => $can,
					'args'                => $items,
				)
			);
		}

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/sync/merge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'merge' ),
				'permission_callback' => $can,
				'args'                => array(
					'kind'    => array(
						'type'     => 'string',
						'enum'     => Sync_Manager::kinds(),
						'required' => true,
					),
					'key'     => array(
						'type'     => 'string',
						'pattern'  => '^(grp|cpt|tax|opt)_[a-z0-9]{6,32}$',
						'required' => true,
					),
					'choices' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/sync/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $can,
				'args'                => array_merge(
					$items,
					array(
						'format' => array(
							'type'    => 'string',
							'enum'    => array( 'json', 'php' ),
							'default' => 'json',
						),
					)
				),
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
	 * State of every definition and where files live.
	 *
	 * @return WP_REST_Response
	 */
	public function status(): WP_REST_Response {
		$files = $this->sync->files();

		return rest_ensure_response(
			array(
				'rows'      => $this->sync->rows(),
				'saveDir'   => $files->save_dir(),
				'loadDirs'  => array_map(
					static fn( string $dir ) => array(
						'path'  => $dir,
						'label' => $files->location_label( $dir . '/x.json' ),
					),
					$files->load_dirs()
				),
				'autoWrite' => $this->sync->auto_write(),
			)
		);
	}

	/**
	 * Differences between the database and file copy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function diff( WP_REST_Request $request ) {
		$diff = $this->sync->diff( (string) $request['kind'], (string) $request['key'] );

		return is_wp_error( $diff ) ? $diff : rest_ensure_response( $diff );
	}

	/**
	 * Write database copies to files.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function push( WP_REST_Request $request ): WP_REST_Response {
		return $this->each( $request, array( $this->sync, 'push' ) );
	}

	/**
	 * Save file (or code) copies to the database.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function pull( WP_REST_Request $request ): WP_REST_Response {
		return $this->each( $request, array( $this->sync, 'pull' ) );
	}

	/**
	 * Merge from a choice per difference.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function merge( WP_REST_Request $request ) {
		$choices = array();

		foreach ( (array) $request['choices'] as $id => $side ) {
			$choices[ (string) $id ] = 'db' === $side ? 'db' : 'file';
		}

		$row = $this->sync->merge( (string) $request['kind'], (string) $request['key'], $choices );

		return is_wp_error( $row ) ? $row : rest_ensure_response( $row );
	}

	/**
	 * Export definitions as JSON files or PHP code.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response {
		$definitions = $this->sync->export( $this->pairs( $request ) );

		if ( 'php' === $request['format'] ) {
			return rest_ensure_response( array( 'code' => "<?php\n" . Php_Exporter::export( $definitions ) ) );
		}

		$files = array();
		foreach ( $definitions as $definition ) {
			$files[] = array(
				'name'    => $definition['item']['key'] . '.json',
				'content' => wp_json_encode(
					array_merge(
						array(
							'key'  => $definition['item']['key'],
							'kind' => $definition['kind'],
						),
						$definition['item'],
						array( 'modified' => time() )
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				),
			);
		}

		return rest_ensure_response( array( 'files' => $files ) );
	}

	/**
	 * Run an action for each requested item, collecting rows and errors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $action  Receives kind and key, returns a row or WP_Error.
	 * @return WP_REST_Response
	 */
	private function each( WP_REST_Request $request, callable $action ): WP_REST_Response {
		$rows   = array();
		$errors = array();

		foreach ( $this->pairs( $request ) as $pair ) {
			$result = $action( $pair['kind'], $pair['key'] );

			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'kind'    => $pair['kind'],
					'key'     => $pair['key'],
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$rows[] = $result;
		}

		return rest_ensure_response(
			array(
				'rows'   => $rows,
				'errors' => $errors,
			)
		);
	}

	/**
	 * Requested kind and key pairs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array{kind: string, key: string}>
	 */
	private function pairs( WP_REST_Request $request ): array {
		$pairs = array();

		foreach ( (array) $request['items'] as $item ) {
			if ( is_array( $item ) && isset( $item['kind'], $item['key'] ) ) {
				$pairs[] = array(
					'kind' => (string) $item['kind'],
					'key'  => (string) $item['key'],
				);
			}
		}

		return $pairs;
	}
}
