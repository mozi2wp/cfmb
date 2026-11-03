<?php
/**
 * Field values that bound blocks may edit in the block editor.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Bindings;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Adds `cfmb_bindings` to posts in the REST API, in the edit context only.
 * It holds the stored values of the post's text-like fields, and saving the
 * post with changes to it saves those fields.
 *
 * The block editor keeps edits made through a bound paragraph in this
 * attribute, so they are saved, undone and marked unsaved together with the
 * rest of the post. Only people who can edit the post see or change it,
 * and every value goes through the field's sanitizing and validation.
 */
class Editor_Values {

	public const ATTRIBUTE = 'cfmb_bindings';

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
	 * Constructor.
	 *
	 * @param Matcher      $matcher Location matcher.
	 * @param Field_Values $values  Value service.
	 */
	public function __construct( Matcher $matcher, Field_Values $values ) {
		$this->matcher = $matcher;
		$this->values  = $values;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_field' ) );
	}

	/**
	 * Field types a bound block may edit on the canvas.
	 *
	 * @return string[]
	 */
	public static function editable_types(): array {
		/**
		 * Filters which field types bound blocks may edit in the block editor.
		 * Only single-value text-like types work: the canvas edits a plain string.
		 *
		 * @param string[] $types Type keys.
		 */
		return array_values( array_map( 'strval', (array) apply_filters( 'cfmb/bindings/editable', array( 'text', 'textarea', 'number', 'email', 'url' ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Add the attribute to post types shown in REST.
	 *
	 * @return void
	 */
	public function register_field(): void {
		register_rest_field(
			array_values( get_post_types( array( 'show_in_rest' => true ) ) ),
			self::ATTRIBUTE,
			array(
				'get_callback'    => array( $this, 'get' ),
				'update_callback' => array( $this, 'update' ),
				'schema'          => array(
					'description'          => __( 'CFMB field values that bound blocks can edit.', 'cfmb' ),
					'type'                 => 'object',
					'context'              => array( 'edit' ),
					'additionalProperties' => array( 'type' => array( 'string', 'number', 'null' ) ),
				),
			)
		);
	}

	/**
	 * Editable values of a post, by field name.
	 *
	 * @param array<string, mixed> $data Prepared response data.
	 * @return object|null
	 */
	public function get( $data ) {
		$id = (int) ( $data['id'] ?? 0 );

		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			return null;
		}

		$ref = new Object_Ref( Object_Ref::POST, $id );
		$out = array();

		foreach ( $this->editable_fields( $id ) as $name => $entry ) {
			$value        = $this->values->load( $entry[0], $entry[1], $ref, false );
			$out[ $name ] = is_scalar( $value ) ? (string) $value : '';
		}

		return (object) $out;
	}

	/**
	 * Save edited values with the post.
	 *
	 * @param mixed   $value New values by field name.
	 * @param WP_Post $post  Post.
	 * @return true|WP_Error
	 */
	public function update( $value, $post ) {
		if ( ! is_array( $value ) || ! $post instanceof WP_Post ) {
			return true;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to edit these fields.', 'cfmb' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$fields = $this->editable_fields( (int) $post->ID );
		$ref    = new Object_Ref( Object_Ref::POST, (int) $post->ID );

		foreach ( $value as $name => $new ) {
			$entry = $fields[ (string) $name ] ?? null;

			if ( ! $entry || ! ( is_scalar( $new ) || null === $new ) ) {
				continue;
			}

			$error = $this->values->save( $entry[0], $entry[1], $ref, null === $new ? '' : (string) $new );

			if ( null !== $error ) {
				return new WP_Error(
					'cfmb_binding_invalid',
					/* translators: 1: field label, 2: error message */
					sprintf( __( '%1$s: %2$s', 'cfmb' ), $entry[0]->label(), $error ),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Top-level fields of the post's groups that bound blocks may edit, by name.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, array{0: Field, 1: Group}>
	 */
	public function editable_fields( int $post_id ): array {
		$types  = self::editable_types();
		$fields = array();
		$seen   = array();
		$ref    = new Object_Ref( Object_Ref::POST, $post_id );

		foreach ( $this->matcher->groups_for( Context::for_post( $post_id, (string) get_post_type( $post_id ) ) ) as $group ) {
			foreach ( $group->fields() as $field ) {
				if ( '' === $field->name() || ! in_array( $field->type(), $types, true ) || isset( $seen[ $field->name() ] ) ) {
					continue;
				}

				$seen[ $field->name() ] = true;

				/** This filter is documented in src/Admin/Group_Renderer.php */
				if ( apply_filters( 'cfmb/field/can_edit', true, $field, $ref, 'bindings' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
					$fields[ $field->name() ] = array( $field, $group );
				}
			}
		}

		return $fields;
	}
}
