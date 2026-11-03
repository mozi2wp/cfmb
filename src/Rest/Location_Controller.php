<?php
/**
 * REST route for location rule choices.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Location\Rule_Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/location/choices?param=post_type
 *
 * Choices are loaded when the builder needs them, so a site with
 * thousands of terms doesn't pay for them on every builder load.
 */
class Location_Controller {

	/**
	 * Location rules.
	 *
	 * @var Rule_Registry
	 */
	private Rule_Registry $rules;

	/**
	 * Constructor.
	 *
	 * @param Rule_Registry $rules Location rules.
	 */
	public function __construct( Rule_Registry $rules ) {
		$this->rules = $rules;
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
			'/location/choices',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'choices' ),
				'permission_callback' => static fn() => current_user_can( cfmb_admin_capability() ),
				'args'                => array(
					'param' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9_]+$',
					),
				),
			)
		);
	}

	/**
	 * Choices for one rule as a list of { value, label }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function choices( WP_REST_Request $request ) {
		$rule = $this->rules->get( (string) $request['param'] );

		if ( ! $rule ) {
			return new WP_Error( 'cfmb_unknown_rule', __( 'That location rule is not available.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
		}

		$choices = array();
		foreach ( $rule->choices() as $value => $label ) {
			$choices[] = array(
				'value' => (string) $value,
				'label' => wp_strip_all_tags( (string) $label ),
			);
		}

		return rest_ensure_response( $choices );
	}
}
