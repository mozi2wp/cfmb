<?php
/**
 * REST route behind the Field Block editor.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Admin\Form_Saver;
use CFMB\Admin\Group_Renderer;
use CFMB\Blocks\Block_Registry;
use CFMB\Blocks\Block_Renderer;
use CFMB\Location\Context;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use CFMB\Storage\Store_Resolver;
use WP_Block;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/blocks/render does three jobs for one Field Block instance:
 *
 * - turns edits from the sidebar form into the cleaned `data` attribute,
 *   with the same sanitizing and validation as a meta box save;
 * - returns the form values the sidebar needs for a block's saved data;
 * - renders the editor preview.
 *
 * Nothing is stored here. The editor puts the returned data into the block,
 * and it is saved with the post.
 */
class Block_Preview_Controller {

	/**
	 * Field Blocks.
	 *
	 * @var Block_Registry
	 */
	private Block_Registry $blocks;

	/**
	 * Renderer.
	 *
	 * @var Block_Renderer
	 */
	private Block_Renderer $renderer;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Storage backends.
	 *
	 * @var Store_Resolver
	 */
	private Store_Resolver $stores;

	/**
	 * Form saving, reused for block values.
	 *
	 * @var Form_Saver
	 */
	private Form_Saver $saver;

	/**
	 * Constructor.
	 *
	 * @param Block_Registry $blocks   Field Blocks.
	 * @param Block_Renderer $renderer Renderer.
	 * @param Field_Values   $values   Value service.
	 * @param Store_Resolver $stores   Storage backends.
	 * @param Form_Saver     $saver    Form saving.
	 */
	public function __construct( Block_Registry $blocks, Block_Renderer $renderer, Field_Values $values, Store_Resolver $stores, Form_Saver $saver ) {
		$this->blocks   = $blocks;
		$this->renderer = $renderer;
		$this->values   = $values;
		$this->stores   = $stores;
		$this->saver    = $saver;
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
			'/blocks/render',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'render' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'name'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'data'       => array(
						'type'    => 'object',
						'default' => array(),
					),
					'values'     => array(
						'type'    => 'object',
						'default' => array(),
					),
					'attributes' => array(
						'type'    => 'object',
						'default' => array(),
					),
					'context'    => array(
						'type'    => 'object',
						'default' => array(),
					),
					'preview'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'form'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * People who edit content, in posts or in the site editor. A post given as
	 * context must be readable by them, since its values may show in the preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_use( WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to edit blocks.', 'custom-fields-meta-box' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$post_id = $this->context_post( $request );

		if ( $post_id && ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to preview this post.', 'custom-fields-meta-box' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Clean data, apply form edits, and return data, form values and preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function render( WP_REST_Request $request ) {
		$name = (string) $request['name'];

		if ( ! $this->blocks->get( $name ) ) {
			return new WP_Error( 'cfmb_block_unknown', __( 'This block is no longer available. Its field group may be inactive or deleted.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
		}

		$ref    = $this->renderer->clean_instance( $name, (array) $request['data'] );
		$errors = array();
		$edits  = $this->submissions( (array) $request['values'] );

		if ( $edits ) {
			$errors = $this->saver->save( $ref, Context::for_block( $name ), false, $edits );
		}

		$data     = $this->stores->blocks()->data( $ref );
		$response = array(
			'data'   => (object) $data,
			'errors' => (object) $errors,
		);

		if ( $request['form'] ) {
			$response['values'] = (object) $this->form_values( $name, $ref );
		}

		$this->stores->blocks()->close( $ref );

		if ( $request['preview'] ) {
			$response['html'] = $this->preview( $name, $data, (array) $request['attributes'], $request );
		}

		return new WP_REST_Response( $response );
	}

	/**
	 * Submitted form values by group key. Each may arrive as an object or a JSON string.
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, array<string, mixed>>
	 */
	private function submissions( array $raw ): array {
		$out = array();

		foreach ( $raw as $group_key => $submitted ) {
			if ( is_string( $submitted ) ) {
				$submitted = json_decode( $submitted, true );
			}

			if ( is_array( $submitted ) ) {
				$out[ sanitize_key( (string) $group_key ) ] = $submitted;
			}
		}

		return $out;
	}

	/**
	 * Editor values of every group of the block, keyed by group, then field key.
	 *
	 * @param string     $name Block name.
	 * @param Object_Ref $ref  Block instance.
	 * @return array<string, object>
	 */
	private function form_values( string $name, Object_Ref $ref ): array {
		$out = array();

		foreach ( $this->renderer->groups( $name ) as $group ) {
			$values = array();

			foreach ( $group->fields() as $field ) {
				$type = $this->values->type_of( $field );

				// Fields the user may not see send no value to the browser.
				if ( $type && $type->stores_value() && 'none' !== Group_Renderer::access( $field, $ref ) ) {
					$values[ $field->key() ] = $this->values->load_for_editor( $field, $group, $ref );
				}
			}

			$out[ $group->key() ] = (object) $values;
		}

		return $out;
	}

	/**
	 * Preview HTML, rendered with the post from the block context set up.
	 *
	 * @param string               $name       Block name.
	 * @param array<string, mixed> $data       Cleaned values.
	 * @param array<string, mixed> $attributes Other block attributes.
	 * @param WP_REST_Request      $request    Request.
	 * @return string
	 */
	private function preview( string $name, array $data, array $attributes, WP_REST_Request $request ): string {
		unset( $attributes['data'] );

		$post_id = $this->context_post( $request );
		$context = array_filter(
			array(
				'postId'   => $post_id,
				'postType' => $post_id ? get_post_type( $post_id ) : '',
			)
		);

		$block = new WP_Block(
			array(
				'blockName'    => $name,
				'attrs'        => array_merge( $attributes, array( 'data' => $data ) ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$context
		);

		$post = $post_id ? get_post( $post_id ) : null;

		if ( $post instanceof WP_Post ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
			setup_postdata( $post );
		}

		try {
			return $this->renderer->render_preview( $block );
		} finally {
			if ( $post instanceof WP_Post ) {
				wp_reset_postdata();
			}
		}
	}

	/**
	 * Post ID from the request's block context, 0 when there is none.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int
	 */
	private function context_post( WP_REST_Request $request ): int {
		$context = (array) $request['context'];
		$post_id = isset( $context['postId'] ) && is_numeric( $context['postId'] ) ? (int) $context['postId'] : 0;

		return $post_id > 0 && get_post( $post_id ) ? $post_id : 0;
	}
}
