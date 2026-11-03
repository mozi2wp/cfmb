<?php
/**
 * Toggle field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * On/off switch. Stored as "1" or "0" so meta_query can match it.
 */
class Toggle_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'toggle';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Toggle', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'choice';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'message', __( 'Text next to the switch', 'cfmb' ), 'text' ),
			$this->setting( 'default_value', __( 'On by default', 'cfmb' ), 'toggle', false ),
		);
	}

	/**
	 * Default as "1" or "0".
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return $field->setting( 'default_value', false ) ? '1' : '0';
	}

	/**
	 * Any truthy input becomes "1".
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true ) ? '1' : '0';
	}

	/**
	 * A required toggle must be switched on.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return '1' !== (string) $value;
	}

	/**
	 * Boolean.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		return '1' === (string) $value;
	}
}
