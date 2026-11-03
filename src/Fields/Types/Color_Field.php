<?php
/**
 * Color field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A color, stored as a hex code (#1e90ff) or, with transparency on,
 * an rgba() value.
 */
class Color_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'color';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Color', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'picker';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'default_value', __( 'Default color', 'cfmb' ), 'text', '', array( 'help' => __( 'A hex code such as #1e90ff.', 'cfmb' ) ) ),
			$this->setting( 'alpha', __( 'Allow transparency', 'cfmb' ), 'toggle', false ),
		);
	}

	/**
	 * Lowercased and trimmed; validation checks the format.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$text = is_scalar( $value ) ? strtolower( trim( sanitize_text_field( (string) $value ) ) ) : '';

		return (string) preg_replace( '/\s+/', '', $text );
	}

	/**
	 * Hex, or rgba() when transparency is allowed.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( self::is_hex( (string) $value ) ) {
			return null;
		}

		if ( $field->setting( 'alpha', false ) && self::is_rgba( (string) $value ) ) {
			return null;
		}

		return __( 'Enter a color as a hex code, like #1e90ff.', 'cfmb' );
	}

	/**
	 * The color code as text.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Whether a value is #rgb, #rrggbb or #rrggbbaa.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_hex( string $value ): bool {
		return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value );
	}

	/**
	 * Whether a value is rgba(r,g,b,a) with channels in range.
	 *
	 * @param string $value Value without spaces.
	 * @return bool
	 */
	public static function is_rgba( string $value ): bool {
		if ( ! preg_match( '/^rgba?\((\d{1,3}),(\d{1,3}),(\d{1,3})(?:,(0|1|0?\.\d+))?\)$/', $value, $parts ) ) {
			return false;
		}

		return (int) $parts[1] <= 255 && (int) $parts[2] <= 255 && (int) $parts[3] <= 255;
	}
}
