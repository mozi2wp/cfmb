<?php
/**
 * Loading, validating and saving field values.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

use CFMB\Core\Hooks;
use CFMB\Fields\Compound_Field;
use CFMB\Fields\Field_Type;
use CFMB\Fields\Registry;
use CFMB\Fields\Structure_Field;
use CFMB\Schema\Field;
use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * The one place values pass through on their way in and out, so every
 * caller (template API, edit screens, REST) gets the same filters,
 * defaults and validation.
 *
 * Group, Repeater and Stacks values are handed to Row_Values, which walks
 * their sub-fields and calls back in here for each one.
 */
class Field_Values {

	/**
	 * Field types.
	 *
	 * @var Registry
	 */
	private Registry $types;

	/**
	 * Storage backend resolver.
	 *
	 * @var Store_Resolver
	 */
	private Store_Resolver $stores;

	/**
	 * Values made of sub-fields.
	 *
	 * @var Row_Values
	 */
	private Row_Values $rows;

	/**
	 * Constructor.
	 *
	 * @param Registry       $types  Field types.
	 * @param Store_Resolver $stores Storage backends.
	 */
	public function __construct( Registry $types, Store_Resolver $stores ) {
		$this->types  = $types;
		$this->stores = $stores;
		$this->rows   = new Row_Values( $this, $stores );
	}

	/**
	 * Field type for a field, or null if the type is not registered.
	 *
	 * @param Field $field Field definition.
	 * @return Field_Type|null
	 */
	public function type_of( Field $field ): ?Field_Type {
		return $this->types->get( $field->type() );
	}

	/**
	 * Row handling for Group, Repeater and Stacks values.
	 *
	 * @return Row_Values
	 */
	public function rows(): Row_Values {
		return $this->rows;
	}

	/**
	 * Whether a field holds sub-fields.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_structure( Field $field ): bool {
		return $this->type_of( $field ) instanceof Structure_Field;
	}

	/**
	 * Load a value.
	 *
	 * @param Field      $field  Field definition.
	 * @param Group|null $group  Owning group.
	 * @param Object_Ref $ref    Object.
	 * @param bool       $format Whether to format for templates.
	 * @return mixed
	 */
	public function load( Field $field, ?Group $group, Object_Ref $ref, bool $format = true ) {
		$type = $this->type_of( $field );

		if ( ! $type || ! $type->stores_value() ) {
			return null;
		}

		if ( $type instanceof Structure_Field ) {
			$raw = $this->rows->load_raw( $field, $group, $ref );
		} elseif ( $type instanceof Compound_Field ) {
			$raw = $this->load_parts( $type, $field, $group, $ref );
		} else {
			$store = $this->stores->for_group( $group, $ref );
			$raw   = $store->exists( $ref, $field->name() )
				? $store->get( $ref, $field->name(), $type->is_multiple( $field ) )
				: $type->default_value( $field );
		}

		/**
		 * Filters a raw value after loading. Also runs as `/type={type}` and `/name={name}`.
		 *
		 * @param mixed      $raw   Raw value.
		 * @param Field      $field Field definition.
		 * @param Object_Ref $ref   Object.
		 */
		$raw = Hooks::filter_field( 'cfmb/field/load_value', $raw, $field, $ref );

		return $format ? $this->format_raw( $field, $raw, $ref ) : $raw;
	}

	/**
	 * Format a raw value for templates.
	 *
	 * @param Field      $field Field definition.
	 * @param mixed      $raw   Raw value.
	 * @param Object_Ref $ref   Object.
	 * @return mixed
	 */
	public function format_raw( Field $field, $raw, Object_Ref $ref ) {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return null;
		}

		$formatted = $type instanceof Structure_Field
			? $this->rows->format( $field, $raw, $ref )
			: $type->format_value( $raw, $field );

		/**
		 * Filters a value formatted for templates. Also runs as `/type={type}` and `/name={name}`.
		 *
		 * @param mixed      $value Formatted value.
		 * @param Field      $field Field definition.
		 * @param Object_Ref $ref   Object.
		 */
		return Hooks::filter_field( 'cfmb/field/format_value', $formatted, $field, $ref );
	}

	/**
	 * Value prepared for the edit screen UI.
	 *
	 * @param Field      $field  Field definition.
	 * @param Group|null $group  Owning group.
	 * @param Object_Ref $ref    Object.
	 * @param bool       $nested Whether the field sits inside another structure. Only top-level rows are paged.
	 * @return mixed
	 */
	public function load_for_editor( Field $field, ?Group $group, Object_Ref $ref, bool $nested = false ) {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return null;
		}

		if ( $type instanceof Structure_Field ) {
			return $this->rows->editor_value( $field, $group, $ref, $nested );
		}

		return $type->editor_value( $this->load( $field, $group, $ref, false ), $field );
	}

	/**
	 * Editor form of a raw value.
	 *
	 * @param Field $field Field definition.
	 * @param mixed $raw   Raw value.
	 * @return mixed
	 */
	public function editor_raw( Field $field, $raw ) {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return null;
		}

		return $type instanceof Structure_Field ? $this->rows->editor( $field, $raw ) : $type->editor_value( $raw, $field );
	}

	/**
	 * Editor value for an object that doesn't exist yet: defaults.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_for_editor( Field $field ) {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return null;
		}

		if ( $type instanceof Structure_Field ) {
			return $this->rows->editor( $field, $this->rows->default_raw( $field ) );
		}

		return $type->editor_value( $type->default_value( $field ), $field );
	}

	/**
	 * Raw default value, sub-fields included.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_raw( Field $field ) {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return null;
		}

		return $type instanceof Structure_Field ? $this->rows->default_raw( $field ) : $type->default_value( $field );
	}

	/**
	 * Sanitize a submitted value.
	 *
	 * @param Field $field Field definition.
	 * @param mixed $value Raw input.
	 * @return mixed
	 */
	public function sanitize( Field $field, $value ) {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return null;
		}

		return $type instanceof Structure_Field ? $this->rows->sanitize( $field, $value ) : $type->sanitize_value( $value, $field );
	}

	/**
	 * Validate a sanitized value.
	 *
	 * @param Field           $field          Field definition.
	 * @param mixed           $value          Sanitized value.
	 * @param bool            $check_required Whether to enforce "required". Off for hidden fields.
	 * @param Object_Ref|null $ref            Object the value is for, when known.
	 * @return string|null Error message, or null when valid.
	 */
	public function validate( Field $field, $value, bool $check_required = true, ?Object_Ref $ref = null ): ?string {
		$errors = $this->validate_tree( $field, $value, $check_required, $ref );

		if ( ! $errors ) {
			return null;
		}

		return $errors[''] ?? __( 'Some fields inside need attention.', 'custom-fields-meta-box' );
	}

	/**
	 * Validate a value and everything inside it.
	 *
	 * Keys are paths below the field: '' for the field itself, "fld_sub" inside
	 * a Group, "2.fld_sub" inside row 2 of a Repeater or Stacks field.
	 *
	 * @param Field           $field          Field definition.
	 * @param mixed           $value          Sanitized value.
	 * @param bool            $check_required Whether to enforce "required".
	 * @param Object_Ref|null $ref            Object the value is for, when known. Sub-fields inside rows get null.
	 * @return array<string, string>
	 */
	public function validate_tree( Field $field, $value, bool $check_required = true, ?Object_Ref $ref = null ): array {
		$type = $this->type_of( $field );

		if ( ! $type ) {
			return array();
		}

		$errors = array();
		$error  = null;

		if ( $type->is_empty( $value ) ) {
			if ( $check_required && $field->required() ) {
				$error = __( 'This field is required.', 'custom-fields-meta-box' );
			}
		} else {
			$error = $type->validate_value( $value, $field );
		}

		/**
		 * Filters a validation result. Return an error message or null.
		 * Also runs as `/type={type}` and `/name={name}`.
		 *
		 * @since 0.10.0 The object is passed as a fourth argument.
		 *
		 * @param string|null     $error Error message or null.
		 * @param Field           $field Field definition.
		 * @param mixed           $value Sanitized value.
		 * @param Object_Ref|null $ref   Object the value is for. Null inside rows and when CFMB doesn't know it.
		 */
		$error = Hooks::filter_field( 'cfmb/field/validate_value', $error, $field, $value, $ref );

		if ( is_string( $error ) && '' !== $error ) {
			$errors[''] = $error;
		}

		if ( $type instanceof Structure_Field ) {
			$errors = array_merge( $errors, $this->rows->errors( $field, $value, $check_required ) );
		}

		return $errors;
	}

	/**
	 * Store a sanitized, validated value.
	 *
	 * @param Field      $field Field definition.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @param mixed      $value Sanitized value.
	 * @return bool
	 */
	public function store( Field $field, ?Group $group, Object_Ref $ref, $value ): bool {
		$type = $this->type_of( $field );

		if ( ! $type || ! $type->stores_value() || '' === $field->name() ) {
			return false;
		}

		/**
		 * Filters a value right before it is stored. Also runs as `/type={type}` and `/name={name}`.
		 *
		 * @param mixed      $value Sanitized value.
		 * @param Field      $field Field definition.
		 * @param Object_Ref $ref   Object.
		 */
		$value = Hooks::filter_field( 'cfmb/field/update_value', $value, $field, $ref );

		if ( $type instanceof Structure_Field ) {
			return $this->rows->store( $field, $group, $ref, $value );
		}

		$store = $this->stores->for_group( $group, $ref );

		if ( $type instanceof Compound_Field ) {
			$parts = is_array( $value ) ? $value : array();

			foreach ( $type->parts() as $part ) {
				$store->set( $ref, $field->name() . '_' . $part, $parts[ $part ] ?? '', false );
			}

			return true;
		}

		return $store->set( $ref, $field->name(), $value, $type->is_multiple( $field ) );
	}

	/**
	 * Sanitize, validate and store in one step. Used by cfmb_update_field().
	 *
	 * @param Field      $field Field definition.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @param mixed      $value Raw value.
	 * @return string|null Error message, or null when saved.
	 */
	public function save( Field $field, ?Group $group, Object_Ref $ref, $value ): ?string {
		$clean = $this->sanitize( $field, $value );
		$error = $this->validate( $field, $clean, false, $ref );

		if ( null !== $error ) {
			return $error;
		}

		$this->store( $field, $group, $ref, $clean );

		return null;
	}

	/**
	 * Remove a stored value, including everything inside it.
	 *
	 * @param Field      $field Field definition.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @return bool
	 */
	public function delete( Field $field, ?Group $group, Object_Ref $ref ): bool {
		$type  = $this->type_of( $field );
		$store = $this->stores->for_group( $group, $ref );

		if ( $type instanceof Structure_Field ) {
			return $this->rows->delete( $field, $group, $ref );
		}

		if ( $type instanceof Compound_Field ) {
			foreach ( $type->parts() as $part ) {
				$store->delete( $ref, $field->name() . '_' . $part );
			}

			return true;
		}

		return $store->delete( $ref, $field->name() );
	}

	/**
	 * Load the parts of a link or map. Falls back to the default when no part was ever saved.
	 *
	 * @param Compound_Field $type  Field type.
	 * @param Field          $field Field definition.
	 * @param Group|null     $group Owning group.
	 * @param Object_Ref     $ref   Object.
	 * @return mixed
	 */
	private function load_parts( Compound_Field $type, Field $field, ?Group $group, Object_Ref $ref ) {
		$store  = $this->stores->for_group( $group, $ref );
		$parts  = array();
		$exists = false;

		foreach ( $type->parts() as $part ) {
			$key = $field->name() . '_' . $part;

			if ( $store->exists( $ref, $key ) ) {
				$exists         = true;
				$parts[ $part ] = $store->get( $ref, $key, false );
			} else {
				$parts[ $part ] = '';
			}
		}

		return $exists ? $parts : $type->default_value( $field );
	}
}
