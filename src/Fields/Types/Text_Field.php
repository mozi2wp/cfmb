<?php
/**
 * Text field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Single line of text.
 */
class Text_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'text';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Text', 'custom-fields-meta-box' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'default_value', __( 'Default value', 'custom-fields-meta-box' ), 'text' ),
			$this->setting( 'placeholder', __( 'Placeholder', 'custom-fields-meta-box' ), 'text' ),
			$this->setting( 'prepend', __( 'Text before the input', 'custom-fields-meta-box' ), 'text', '', array( 'advanced' => true ) ),
			$this->setting( 'append', __( 'Text after the input', 'custom-fields-meta-box' ), 'text', '', array( 'advanced' => true ) ),
			$this->setting(
				'maxlength',
				__( 'Character limit', 'custom-fields-meta-box' ),
				'number',
				'',
				array(
					'advanced' => true,
					'help'     => __( 'Leave empty for no limit.', 'custom-fields-meta-box' ),
				)
			),
		);
	}

	/**
	 * Enforce the character limit.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$limit = (int) $field->setting( 'maxlength', 0 );

		if ( $limit > 0 && is_string( $value ) && mb_strlen( $value ) > $limit ) {
			return sprintf(
				/* translators: %d: maximum number of characters */
				_n( 'Use %d character or fewer.', 'Use %d characters or fewer.', $limit, 'custom-fields-meta-box' ),
				$limit
			);
		}

		return null;
	}
}
