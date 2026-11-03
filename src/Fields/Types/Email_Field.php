<?php
/**
 * Email field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Email address.
 */
class Email_Field extends Text_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'email';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Email', 'custom-fields-meta-box' );
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
		);
	}

	/**
	 * Valid addresses are normalized; anything else is kept for the error message.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$text = is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';

		return is_email( $text ) ? sanitize_email( $text ) : $text;
	}

	/**
	 * Must be an email address.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( '' === $value || is_email( (string) $value ) ) {
			return null;
		}

		return __( 'Enter an email address, like name@example.com.', 'custom-fields-meta-box' );
	}

	/**
	 * Address with HTML entities obfuscated against scrapers.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		return esc_html( antispambot( $this->scalar_text( $formatted ) ) );
	}
}
