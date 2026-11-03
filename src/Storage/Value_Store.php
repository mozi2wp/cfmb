<?php
/**
 * Contract for value storage backends.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes raw field values for one object.
 *
 * Meta storage is the default. CFMB Pro's Table Storage implements this
 * same interface and is selected through the `cfmb/storage/store` filter.
 */
interface Value_Store {

	/**
	 * Whether a value was ever saved.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function exists( Object_Ref $ref, string $name ): bool;

	/**
	 * Read a raw value.
	 *
	 * @param Object_Ref $ref      Object.
	 * @param string     $name     Field name.
	 * @param bool       $multiple Whether the value is a list.
	 * @return mixed A list when $multiple, otherwise a single value.
	 */
	public function get( Object_Ref $ref, string $name, bool $multiple );

	/**
	 * Write a raw value, replacing what was there.
	 *
	 * @param Object_Ref $ref      Object.
	 * @param string     $name     Field name.
	 * @param mixed      $value    Sanitized value.
	 * @param bool       $multiple Whether the value is a list.
	 * @return bool
	 */
	public function set( Object_Ref $ref, string $name, $value, bool $multiple ): bool;

	/**
	 * Remove a value.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function delete( Object_Ref $ref, string $name ): bool;
}
