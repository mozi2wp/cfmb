<?php
/**
 * Stacks field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Structure_Base;
use CFMB\Fields\Structure_Field;

defined( 'ABSPATH' ) || exit;

/**
 * Rows that each pick one of several layouts, stacked in any order.
 *
 * The field name holds one meta row per stack row with its layout name, in
 * order (sections = hero, cta, hero), so meta_query can find posts that use
 * a layout. Values are stored as {name}_{row}_{sub}.
 */
class Stacks_Field extends Structure_Base {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'stacks';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Stacks', 'cfmb' );
	}

	/**
	 * Shape.
	 *
	 * @return string
	 */
	public function structure(): string {
		return Structure_Field::STACKS;
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return $this->row_settings();
	}

	/**
	 * Empty when there are no rows.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return ! is_array( $value ) || array() === $value;
	}
}
