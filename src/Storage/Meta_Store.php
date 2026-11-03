<?php
/**
 * Metadata storage.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Stores values in post, term and user meta under the field name.
 *
 * Lists are stored one meta row per item, so meta_query can match a single
 * item with "=" or "IN" instead of a LIKE search on serialized data.
 * Reads go through the meta cache, which WordPress primes for whole loops.
 */
class Meta_Store implements Value_Store {

	/**
	 * Whether a value was ever saved.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function exists( Object_Ref $ref, string $name ): bool {
		return metadata_exists( $ref->meta_type(), $ref->id(), $name );
	}

	/**
	 * Read a raw value.
	 *
	 * @param Object_Ref $ref      Object.
	 * @param string     $name     Field name.
	 * @param bool       $multiple Whether the value is a list.
	 * @return mixed
	 */
	public function get( Object_Ref $ref, string $name, bool $multiple ) {
		if ( $multiple ) {
			$values = get_metadata( $ref->meta_type(), $ref->id(), $name, false );

			if ( ! is_array( $values ) ) {
				return array();
			}

			// Drop the empty marker row written for a saved empty list.
			return array_values( array_filter( $values, static fn( $item ) => '' !== $item ) );
		}

		return get_metadata( $ref->meta_type(), $ref->id(), $name, true );
	}

	/**
	 * Write a raw value.
	 *
	 * @param Object_Ref $ref      Object.
	 * @param string     $name     Field name.
	 * @param mixed      $value    Sanitized value.
	 * @param bool       $multiple Whether the value is a list.
	 * @return bool
	 */
	public function set( Object_Ref $ref, string $name, $value, bool $multiple ): bool {
		$type = $ref->meta_type();
		$id   = $ref->id();

		if ( ! $multiple ) {
			// update_metadata() returns false when the value is unchanged; that is still a success.
			update_metadata( $type, $id, $name, wp_slash( $value ) );

			return true;
		}

		$items  = array_values( (array) $value );
		$stored = metadata_exists( $type, $id, $name ) ? get_metadata( $type, $id, $name, false ) : null;

		// Compare as strings: meta comes back as strings, sanitized IDs may be ints.
		if ( is_array( $stored ) && array_map( 'strval', $stored ) === array_map( 'strval', array() === $items ? array( '' ) : $items ) ) {
			return true;
		}

		delete_metadata( $type, $id, $name );

		foreach ( $items as $item ) {
			add_metadata( $type, $id, $name, wp_slash( $item ) );
		}

		// Keeps "was saved" true for an empty list, so defaults don't come back.
		if ( array() === $items ) {
			add_metadata( $type, $id, $name, '' );
		}

		return true;
	}

	/**
	 * Remove a value.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function delete( Object_Ref $ref, string $name ): bool {
		return delete_metadata( $ref->meta_type(), $ref->id(), $name );
	}
}
