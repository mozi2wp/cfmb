<?php
/**
 * Checkbox list field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Choice_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * List of checkboxes. Each checked value is its own meta row.
 */
class Checkbox_Field extends Choice_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'checkbox';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Checkboxes', 'custom-fields-meta-box' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->choices_setting(),
			$this->default_setting( true ),
			$this->layout_setting(),
			$this->setting( 'toggle_all', __( 'Show a "Select all" checkbox', 'custom-fields-meta-box' ), 'toggle', false, array( 'advanced' => true ) ),
			$this->return_format_setting(),
		);
	}

	/**
	 * Always a list.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return true;
	}
}
