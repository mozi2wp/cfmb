<?php
/**
 * Textarea field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Multiple lines of plain text.
 */
class Textarea_Field extends Text_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'textarea';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Text area', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'default_value', __( 'Default value', 'cfmb' ), 'textarea' ),
			$this->setting( 'placeholder', __( 'Placeholder', 'cfmb' ), 'text' ),
			$this->setting( 'rows', __( 'Rows', 'cfmb' ), 'number', 4, array( 'advanced' => true ) ),
			$this->setting( 'maxlength', __( 'Character limit', 'cfmb' ), 'number', '', array( 'advanced' => true ) ),
			$this->setting(
				'new_lines',
				__( 'Line breaks in output', 'cfmb' ),
				'select',
				'wpautop',
				array(
					'advanced' => true,
					'options'  => array(
						'wpautop' => __( 'Wrap paragraphs', 'cfmb' ),
						'br'      => __( 'Add line breaks', 'cfmb' ),
						'none'    => __( 'Plain text', 'cfmb' ),
					),
				)
			),
		);
	}

	/**
	 * Keep line breaks.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	/**
	 * Escaped text with the chosen line break handling.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$text = esc_html( $this->scalar_text( $formatted ) );

		switch ( $field->setting( 'new_lines', 'wpautop' ) ) {
			case 'br':
				return nl2br( $text );
			case 'none':
				return $text;
			default:
				return wpautop( $text );
		}
	}
}
