<?php
/**
 * Field values in core REST API responses.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Post;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a read-only `cfmb` object to posts, terms, users and comments in the
 * REST API, with the values of groups that have "Show in REST" turned on.
 * Fields can opt out one by one.
 *
 * Values are the stored ones: IDs for images and posts, ISO dates, rows as
 * lists of objects. Password-protected posts show no values to visitors,
 * and user fields only show to people who can edit that user.
 */
class Field_Values_Rest {

	/**
	 * Attribute name in responses.
	 */
	public const ATTRIBUTE = 'cfmb';

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
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Constructor.
	 *
	 * @param Matcher          $matcher Location matcher.
	 * @param Field_Values     $values  Value service.
	 * @param Group_Repository $groups  Field Groups.
	 */
	public function __construct( Matcher $matcher, Field_Values $values, Group_Repository $groups ) {
		$this->matcher = $matcher;
		$this->values  = $values;
		$this->groups  = $groups;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Add the attribute to every object type shown in REST.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		if ( ! $this->any_group_in_rest() ) {
			return;
		}

		$types = array_merge(
			array_values( get_post_types( array( 'show_in_rest' => true ) ) ),
			array_values( get_taxonomies( array( 'show_in_rest' => true ) ) ),
			array( 'user', 'comment' )
		);

		$schema = array(
			'description' => __( 'Values of CFMB fields in groups shown in REST.', 'custom-fields-meta-box' ),
			'type'        => 'object',
			'context'     => array( 'view', 'edit' ),
			'readonly'    => true,
		);

		foreach ( $types as $object_type ) {
			/**
			 * Filters the schema of the `cfmb` attribute for one object type,
			 * so it can list the fields it holds and their types.
			 *
			 * @since 0.16.0
			 *
			 * @param array<string, mixed> $schema      JSON Schema of the attribute.
			 * @param string               $object_type Post type, taxonomy, user or comment.
			 */
			$typed = apply_filters( 'cfmb/rest/schema', $schema, (string) $object_type ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			register_rest_field(
				(string) $object_type,
				self::ATTRIBUTE,
				array(
					'get_callback' => array( $this, 'get_values' ),
					'schema'       => is_array( $typed ) ? $typed : $schema,
				)
			);
		}
	}

	/**
	 * Values for one object.
	 *
	 * @param array<string, mixed> $data        Prepared response data.
	 * @param string               $attribute   Attribute name.
	 * @param WP_REST_Request      $request     Request.
	 * @param string               $object_type Post type, taxonomy, user or comment.
	 * @return object
	 */
	public function get_values( $data, $attribute, $request, $object_type ) {
		$id  = (int) ( $data['id'] ?? 0 );
		$ref = $this->ref_for( (string) $object_type, $id );

		if ( ! $ref || ! $this->may_read( $ref ) ) {
			return (object) array();
		}

		$out = array();

		foreach ( $this->matcher->groups_for( Context::from_ref( $ref ) ) as $group ) {
			if ( ! $group->setting( 'show_in_rest' ) ) {
				continue;
			}

			foreach ( $group->fields() as $field ) {
				$type = $this->values->type_of( $field );

				if ( '' === $field->name() || ! $field->in_rest() || ! $type || ! $type->stores_value() || array_key_exists( $field->name(), $out ) ) {
					continue;
				}

				$value = $this->values->load( $field, $group, $ref, false );

				/**
				 * Filters a field value in REST responses. Return null to leave the field out.
				 *
				 * @param mixed              $value   Stored value.
				 * @param \CFMB\Schema\Field $field   Field.
				 * @param Object_Ref         $ref     Object.
				 * @param WP_REST_Request    $request Request.
				 */
				$value = apply_filters( 'cfmb/rest/value', $value, $field, $ref, $request ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

				if ( null !== $value ) {
					$out[ $field->name() ] = $value;
				}
			}
		}

		return (object) $out;
	}

	/**
	 * Object reference for a REST object type.
	 *
	 * @param string $object_type Post type, taxonomy, user or comment.
	 * @param int    $id          Object ID.
	 * @return Object_Ref|null
	 */
	private function ref_for( string $object_type, int $id ): ?Object_Ref {
		if ( $id <= 0 ) {
			return null;
		}

		if ( 'user' === $object_type ) {
			return new Object_Ref( Object_Ref::USER, $id );
		}

		if ( 'comment' === $object_type ) {
			return new Object_Ref( Object_Ref::COMMENT, $id );
		}

		if ( taxonomy_exists( $object_type ) ) {
			return new Object_Ref( Object_Ref::TERM, $id );
		}

		return post_type_exists( $object_type ) ? new Object_Ref( Object_Ref::POST, $id ) : null;
	}

	/**
	 * Extra checks on top of what the REST endpoint already checked.
	 *
	 * @param Object_Ref $ref Object.
	 * @return bool
	 */
	private function may_read( Object_Ref $ref ): bool {
		if ( Object_Ref::USER === $ref->kind() ) {
			return current_user_can( 'edit_user', $ref->id() );
		}

		if ( Object_Ref::POST === $ref->kind() ) {
			$post = get_post( $ref->id() );

			return $post instanceof WP_Post && ( ! post_password_required( $post ) || current_user_can( 'edit_post', $post->ID ) );
		}

		return true;
	}

	/**
	 * Whether any active group has Show in REST on, so the attribute is only
	 * added when it can hold something.
	 *
	 * @return bool
	 */
	private function any_group_in_rest(): bool {
		foreach ( $this->groups->active() as $group ) {
			if ( $group instanceof Group && $group->setting( 'show_in_rest' ) ) {
				return true;
			}
		}

		return false;
	}
}
