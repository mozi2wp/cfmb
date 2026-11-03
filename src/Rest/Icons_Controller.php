<?php
/**
 * REST route for the icon picker.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Icons\Icon_Library_Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/icons: every icon of a library, loaded when the picker opens.
 */
class Icons_Controller {

	/**
	 * Icon libraries.
	 *
	 * @var Icon_Library_Registry
	 */
	private Icon_Library_Registry $libraries;

	/**
	 * Constructor.
	 *
	 * @param Icon_Library_Registry $libraries Icon libraries.
	 */
	public function __construct( Icon_Library_Registry $libraries ) {
		$this->libraries = $libraries;
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
			'/icons',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'icons' ),
				'permission_callback' => static fn( WP_REST_Request $request ) => is_user_logged_in() || Form_Access::allows( 'icons', $request ),
				'args'                => array(
					'library' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9_-]+$',
					),
				),
			)
		);
	}

	/**
	 * Icons of one library.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function icons( WP_REST_Request $request ) {
		$library = $this->libraries->get( (string) $request['library'] );

		if ( ! $library ) {
			return new WP_Error( 'cfmb_icon_library', __( 'That icon library is not available.', 'cfmb' ), array( 'status' => 404 ) );
		}

		$icons = array();
		foreach ( $library->icons() as $name => $icon ) {
			$icons[] = array_merge( array( 'name' => (string) $name ), $icon );
		}

		$response = rest_ensure_response( $icons );
		$response->header( 'Cache-Control', 'private, max-age=86400' );

		return $response;
	}
}
