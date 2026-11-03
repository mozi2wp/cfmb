<?php
/**
 * Number field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Integer or decimal number.
 */
class Number_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'number';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Number', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'default_value', __( 'Default value', 'cfmb' ), 'number' ),
			$this->setting( 'placeholder', __( 'Placeholder', 'cfmb' ), 'text' ),
			$this->setting( 'min', __( 'Minimum', 'cfmb' ), 'number' ),
			$this->setting( 'max', __( 'Maximum', 'cfmb' ), 'number' ),
			$this->setting( 'step', __( 'Step', 'cfmb' ), 'number', '', array( 'advanced' => true ) ),
			$this->setting( 'prepend', __( 'Text before the input', 'cfmb' ), 'text', '', array( 'advanced' => true ) ),
			$this->setting( 'append', __( 'Text after the input', 'cfmb' ), 'text', '', array( 'advanced' => true ) ),
		);
	}

	/**
	 * Store numbers as plain numeric strings so meta_query NUMERIC works.
	 * Anything that is not a number is kept as text so validation can report it.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		return is_numeric( $value ) ? (string) ( $value + 0 ) : sanitize_text_field( $value );
	}

	/**
	 * Check type and bounds.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( '' === $value || null === $value ) {
			return null;
		}

		if ( ! is_numeric( $value ) ) {
			return __( 'Enter a number.', 'cfmb' );
		}

		$min = $field->setting( 'min', '' );
		$max = $field->setting( 'max', '' );

		if ( is_numeric( $min ) && $value + 0 < $min + 0 ) {
			/* translators: %s: minimum allowed number */
			return sprintf( __( 'Enter a number of %s or more.', 'cfmb' ), number_format_i18n( $min + 0, $this->decimals( $min ) ) );
		}

		if ( is_numeric( $max ) && $value + 0 > $max + 0 ) {
			/* translators: %s: maximum allowed number */
			return sprintf( __( 'Enter a number of %s or less.', 'cfmb' ), number_format_i18n( $max + 0, $this->decimals( $max ) ) );
		}

		return null;
	}

	/**
	 * Return int or float, or null when empty.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return int|float|null
	 */
	public function format_value( $value, Field $field ) {
		return is_numeric( $value ) ? $value + 0 : null;
	}

	/**
	 * Locale-formatted number.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		if ( ! is_numeric( $formatted ) ) {
			return '';
		}

		return esc_html( number_format_i18n( $formatted + 0, $this->decimals( $formatted ) ) );
	}

	/**
	 * Decimal places in a numeric value, so formatting never rounds it.
	 *
	 * @param mixed $number Number.
	 * @return int
	 */
	private function decimals( $number ): int {
		$text = (string) $number;
		$dot  = strpos( $text, '.' );

		return false === $dot ? 0 : strlen( $text ) - $dot - 1;
	}
}
