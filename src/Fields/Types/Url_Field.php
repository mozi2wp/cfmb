<?php
/**
 * URL field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Web address.
 */
class Url_Field extends Text_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'url';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'URL', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'default_value', __( 'Default value', 'cfmb' ), 'text' ),
			$this->setting( 'placeholder', __( 'Placeholder', 'cfmb' ), 'text', 'https://' ),
		);
	}

	/**
	 * Clean URLs are stored; unusable input is kept so validation can report it.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$text = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $text ) {
			return '';
		}

		$url = esc_url_raw( $text );

		return '' !== $url ? $url : sanitize_text_field( $text );
	}

	/**
	 * Must be a URL.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( '' === $value || false !== filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		return __( 'Enter a full web address, like https://example.com.', 'cfmb' );
	}

	/**
	 * Escaped URL.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		return esc_url( $this->scalar_text( $formatted ) );
	}
}
