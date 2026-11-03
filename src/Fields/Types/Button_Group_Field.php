<?php
/**
 * Button group field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Choice_Field;

defined( 'ABSPATH' ) || exit;

/**
 * One choice from a row of buttons. Good for short lists like alignment.
 */
class Button_Group_Field extends Choice_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'button_group';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Button group', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->choices_setting(),
			$this->setting( 'allow_null', __( 'Allow no choice', 'cfmb' ), 'toggle', false ),
			$this->default_setting( false ),
			$this->return_format_setting(),
		);
	}
}
