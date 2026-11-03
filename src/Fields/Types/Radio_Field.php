<?php
/**
 * Radio button field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Choice_Field;

defined( 'ABSPATH' ) || exit;

/**
 * One choice from a list of radio buttons.
 */
class Radio_Field extends Choice_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'radio';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Radio buttons', 'custom-fields-meta-box' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->choices_setting(),
			$this->default_setting( false ),
			$this->layout_setting(),
			$this->return_format_setting(),
		);
	}
}
