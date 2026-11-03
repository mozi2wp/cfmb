<?php
/**
 * REST routes for the Switch Assistant.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Switching\Switch_Assistant;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/switch: scan, import, and per-run convert, undo and forget.
 * Admins only (cfmb_admin_capability()). `source` picks another plugin
 * registered with `cfmb/switch/sources` (since 0.15.0); without it ACF and
 * SCF are used.
 */
class Switch_Controller {

	/**
	 * Switch Assistant.
	 *
	 * @var Switch_Assistant
	 */
	private Switch_Assistant $assistant;

	/**
	 * Constructor.
	 *
	 * @param Switch_Assistant $assistant Switch Assistant.
	 */
	public function __construct( Switch_Assistant $assistant ) {
		$this->assistant = $assistant;
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
		$can = static fn(): bool => current_user_can( cfmb_admin_capability() );

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/switch',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'scan' ),
				'permission_callback' => $can,
				'args'                => array(
					'source' => self::source_arg(),
				),
			)
		);

		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/switch/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $can,
				'args'                => array(
					'groups'        => self::list_arg(),
					'post_types'    => self::list_arg(),
					'taxonomies'    => self::list_arg(),
					'options_pages' => self::list_arg(),
					'source'        => self::source_arg(),
					'picked'        => array(
						'type'                 => 'object',
						'default'              => array(),
						'additionalProperties' => self::list_arg(),
					),
				),
			)
		);

		foreach ( array( 'convert', 'undo', 'forget' ) as $action ) {
			register_rest_route(
				Groups_Controller::REST_NAMESPACE,
				'/switch/runs/(?P<run>run_[0-9]{14}_[a-f0-9]{6})/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action ),
					'permission_callback' => $can,
				)
			);
		}
	}

	/**
	 * What can be imported.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function scan( WP_REST_Request $request ) {
		$source = (string) $request['source'];
		$result = '' === $source ? $this->assistant->scan() : $this->assistant->scan_source( $source );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Import picked definitions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$source = (string) $request['source'];

		if ( '' !== $source ) {
			$result = $this->assistant->import_source( $source, (array) $request['picked'] );

			return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
		}

		$result = $this->assistant->import(
			array(
				'groups'        => (array) $request['groups'],
				'post_types'    => (array) $request['post_types'],
				'taxonomies'    => (array) $request['taxonomies'],
				'options_pages' => (array) $request['options_pages'],
			)
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Convert the next batch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function convert( WP_REST_Request $request ) {
		$result = $this->assistant->convert( (string) $request['run'] );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Undo the next batch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function undo( WP_REST_Request $request ) {
		$result = $this->assistant->undo( (string) $request['run'] );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Drop a run and its backup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function forget( WP_REST_Request $request ) {
		if ( ! $this->assistant->forget( (string) $request['run'] ) ) {
			return new WP_Error( 'cfmb_switch_run', __( 'That import was not found. Reload the page.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'forgotten' => true ) );
	}

	/**
	 * A source ID, empty for ACF and SCF.
	 *
	 * @return array<string, mixed>
	 */
	private static function source_arg(): array {
		return array(
			'type'    => 'string',
			'pattern' => '^[a-z0-9-]{0,20}$',
			'default' => '',
		);
	}

	/**
	 * A list of keys.
	 *
	 * @return array<string, mixed>
	 */
	private static function list_arg(): array {
		return array(
			'type'    => 'array',
			'items'   => array(
				'type'    => 'string',
				'pattern' => '^[A-Za-z0-9_:.-]{1,150}$',
			),
			'default' => array(),
		);
	}
}
