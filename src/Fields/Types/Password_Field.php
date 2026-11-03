<?php
/**
 * Password field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Masked text input. Stored as entered, so it is not suitable for
 * secrets that must be hashed; that is the site's own login system's job.
 */
class Password_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'password';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Password', 'custom-fields-meta-box' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'placeholder', __( 'Placeholder', 'custom-fields-meta-box' ), 'text' ),
		);
	}

	/**
	 * Keep every printable character; sanitize_text_field() would strip
	 * characters like < that are legitimate in passwords.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		return is_scalar( $value ) ? (string) preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $value ) : '';
	}

	/**
	 * No default for passwords.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return '';
	}
}
