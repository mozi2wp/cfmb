<?php
/**
 * Select field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Choice_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Dropdown, single or multiple.
 */
class Select_Field extends Choice_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'select';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Select', 'custom-fields-meta-box' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->choices_setting(),
			$this->setting( 'multiple', __( 'Allow several choices', 'custom-fields-meta-box' ), 'toggle', false ),
			$this->setting( 'allow_null', __( 'Allow no choice', 'custom-fields-meta-box' ), 'toggle', false ),
			$this->default_setting( true ),
			$this->setting( 'placeholder', __( 'Placeholder', 'custom-fields-meta-box' ), 'text', '', array( 'advanced' => true ) ),
			$this->return_format_setting(),
		);
	}

	/**
	 * Multiple when the setting is on.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return (bool) $field->setting( 'multiple', false );
	}
}
