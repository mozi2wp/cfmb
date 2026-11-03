<?php
/**
 * Range field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

defined( 'ABSPATH' ) || exit;

/**
 * Number picked with a slider.
 */
class Range_Field extends Number_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'range';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Range', 'cfmb' );
	}

	/**
	 * Builder settings. A slider always needs bounds.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'default_value', __( 'Default value', 'cfmb' ), 'number' ),
			$this->setting( 'min', __( 'Minimum', 'cfmb' ), 'number', 0 ),
			$this->setting( 'max', __( 'Maximum', 'cfmb' ), 'number', 100 ),
			$this->setting( 'step', __( 'Step', 'cfmb' ), 'number', 1 ),
			$this->setting( 'prepend', __( 'Text before the input', 'cfmb' ), 'text', '', array( 'advanced' => true ) ),
			$this->setting( 'append', __( 'Text after the input', 'cfmb' ), 'text', '', array( 'advanced' => true ) ),
		);
	}
}
