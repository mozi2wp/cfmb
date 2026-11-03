<?php
/**
 * Repeater field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Structure_Base;
use CFMB\Fields\Structure_Field;

defined( 'ABSPATH' ) || exit;

/**
 * Rows of the same sub-fields. Stores the row count under the field name
 * (items = 2) and each value as {name}_{row}_{sub} (items_0_title).
 */
class Repeater_Field extends Structure_Base {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'repeater';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Repeater', 'cfmb' );
	}

	/**
	 * Shape.
	 *
	 * @return string
	 */
	public function structure(): string {
		return Structure_Field::ROWS;
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		$settings = $this->row_settings();

		array_splice(
			$settings,
			3,
			0,
			array(
				$this->setting(
					'row_title',
					__( 'Row title', 'cfmb' ),
					'subfield',
					'',
					array( 'help' => __( 'Sub-field whose value names each row when it is collapsed.', 'cfmb' ) )
				),
			)
		);

		return $settings;
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
