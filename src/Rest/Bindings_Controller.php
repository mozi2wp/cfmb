<?php
/**
 * REST route for bound values in the block editor.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Display\Formatter;
use CFMB\Display\Resolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/bindings: what bound blocks show in the editor, for one object
 * and a list of field names. Same visibility rules as the front end, applied
 * for the person editing.
 */
class Bindings_Controller {

	/**
	 * Source lookup.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Value formatting.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param Resolver  $resolver  Source lookup.
	 * @param Formatter $formatter Value formatting.
	 */
	public function __construct( Resolver $resolver, Formatter $formatter ) {
		$this->resolver  = $resolver;
		$this->formatter = $formatter;
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
			'/bindings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'values' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'fields' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
					'object' => array(
						'type'    => 'string',
						'default' => '',
					),
					'post'   => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
	}

	/**
	 * People who edit content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_use( WP_REST_Request $request ) {
		$post = (int) $request['post'];

		if ( ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_theme_options' ) ) && ( ! $post || current_user_can( 'read_post', $post ) ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'You are not allowed to preview field values.', 'cfmb' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Display parts for each requested field, null when it can't be shown.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function values( WP_REST_Request $request ): WP_REST_Response {
		$out = array();

		foreach ( array_slice( array_unique( array_map( 'strval', (array) $request['fields'] ) ), 0, 50 ) as $name ) {
			$found        = $this->resolver->find( $name, (string) $request['object'], (int) $request['post'] );
			$out[ $name ] = $found && $this->formatter->supports( $found['field'] )
				? array_merge(
					$this->formatter->parts( $found['field'], $found['group'], $found['ref'] ),
					array( 'label' => $found['field']->label() )
				)
				: null;
		}

		return new WP_REST_Response( (object) $out );
	}
}
