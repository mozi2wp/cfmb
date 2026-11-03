<?php
/**
 * Values of Field Block instances.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps each block instance's values in memory while it renders or is
 * converted, under the same flattened names meta uses (items = 2,
 * items_0_title). The values themselves live in the block's `data`
 * attribute; this store only lets Field_Values and Row_Values read and
 * write them like any other object.
 */
class Block_Store implements Value_Store {

	/**
	 * Values per block instance, keyed by instance ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $data = array();

	/**
	 * Block name per instance, e.g. cfmb/hero.
	 *
	 * @var array<string, string>
	 */
	private array $names = array();

	/**
	 * Instances opened in this request, for unique IDs.
	 *
	 * @var int
	 */
	private int $opened = 0;

	/**
	 * Start a block instance with its stored values.
	 *
	 * @param string               $block Block name.
	 * @param array<string, mixed> $data  Values by flattened name.
	 * @return Object_Ref
	 */
	public function open( string $block, array $data ): Object_Ref {
		$id  = 'b' . ( ++$this->opened );
		$ref = new Object_Ref( Object_Ref::BLOCK, 0, $id );

		$this->data[ $id ]  = $data;
		$this->names[ $id ] = $block;

		return $ref;
	}

	/**
	 * Forget a block instance.
	 *
	 * @param Object_Ref $ref Block instance.
	 * @return void
	 */
	public function close( Object_Ref $ref ): void {
		unset( $this->data[ $ref->page() ], $this->names[ $ref->page() ] );
	}

	/**
	 * Every value of an instance by flattened name, in a stable order.
	 *
	 * @param Object_Ref $ref Block instance.
	 * @return array<string, mixed>
	 */
	public function data( Object_Ref $ref ): array {
		$data = $this->data[ $ref->page() ] ?? array();
		ksort( $data, SORT_STRING );

		return $data;
	}

	/**
	 * Block name of an instance.
	 *
	 * @param Object_Ref $ref Block instance.
	 * @return string
	 */
	public function block_name( Object_Ref $ref ): string {
		return $this->names[ $ref->page() ] ?? '';
	}

	/**
	 * Whether a value was ever saved.
	 *
	 * @param Object_Ref $ref  Block instance.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function exists( Object_Ref $ref, string $name ): bool {
		return isset( $this->data[ $ref->page() ] ) && array_key_exists( $name, $this->data[ $ref->page() ] );
	}

	/**
	 * Read a raw value.
	 *
	 * @param Object_Ref $ref      Block instance.
	 * @param string     $name     Field name.
	 * @param bool       $multiple Whether the value is a list.
	 * @return mixed
	 */
	public function get( Object_Ref $ref, string $name, bool $multiple ) {
		$value = $this->data[ $ref->page() ][ $name ] ?? ( $multiple ? array() : '' );

		if ( $multiple ) {
			return is_array( $value ) ? array_values( $value ) : ( '' === $value || null === $value ? array() : array( $value ) );
		}

		// Attributes come from JSON; keep scalars as the strings meta would return.
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		return is_int( $value ) || is_float( $value ) ? (string) $value : $value;
	}

	/**
	 * Write a raw value.
	 *
	 * @param Object_Ref $ref      Block instance.
	 * @param string     $name     Field name.
	 * @param mixed      $value    Sanitized value.
	 * @param bool       $multiple Whether the value is a list.
	 * @return bool
	 */
	public function set( Object_Ref $ref, string $name, $value, bool $multiple ): bool {
		if ( ! isset( $this->data[ $ref->page() ] ) ) {
			return false;
		}

		$this->data[ $ref->page() ][ $name ] = $multiple ? array_values( (array) $value ) : $value;

		return true;
	}

	/**
	 * Remove a value.
	 *
	 * @param Object_Ref $ref  Block instance.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function delete( Object_Ref $ref, string $name ): bool {
		unset( $this->data[ $ref->page() ][ $name ] );

		return true;
	}
}
