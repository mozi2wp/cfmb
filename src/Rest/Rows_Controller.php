<?php
/**
 * REST route for loading pages of Repeater and Stacks rows.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/rows: stored rows of a paged field, by index, in editor form.
 */
class Rows_Controller {

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Permission checks.
	 *
	 * @var Object_Access
	 */
	private Object_Access $access;

	/**
	 * Constructor.
	 *
	 * @param Matcher       $matcher Location matcher.
	 * @param Field_Values  $values  Value service.
	 * @param Object_Access $access  Permission checks.
	 */
	public function __construct( Matcher $matcher, Field_Values $values, Object_Access $access ) {
		$this->matcher = $matcher;
		$this->values  = $values;
		$this->access  = $access;
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
			'/rows',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rows' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'object'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'group'   => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^grp_[a-z0-9]{6,32}$',
					),
					'field'   => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^fld_[a-z0-9_]{6,80}$',
					),
					'indexes' => array(
						'type'     => 'array',
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'maxItems' => 200,
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Only people who can edit the object, or a front-end form editing it
	 * that an add-on vouches for.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_read( WP_REST_Request $request ): bool {
		$ref = Object_Ref::from( (string) $request['object'] );

		if ( ! $ref instanceof Object_Ref ) {
			return false;
		}

		return $this->access->can_edit( $ref ) || Form_Access::allows( 'rows', $request );
	}

	/**
	 * Rows by stored index.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rows( WP_REST_Request $request ) {
		$ref   = Object_Ref::from( (string) $request['object'] );
		$group = $ref ? $this->matcher->matching_group( (string) $request['group'], Context::from_ref( $ref ) ) : null;

		if ( ! $ref || ! $group ) {
			return new WP_Error( 'cfmb_rows_unavailable', __( 'These fields are not shown for this item.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
		}

		// Only top-level fields are paged, so their storage name is their own name.
		$field = null;
		foreach ( $group->fields() as $candidate ) {
			if ( $candidate->key() === (string) $request['field'] ) {
				$field = $candidate;
				break;
			}
		}

		if ( ! $field || ! $this->values->rows()->is_paged( $field ) ) {
			return new WP_Error( 'cfmb_rows_unavailable', __( 'That field does not load rows in pages.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			$this->values->rows()->editor_rows( $field, $group, $ref, array_map( 'intval', (array) $request['indexes'] ) )
		);
	}
}
