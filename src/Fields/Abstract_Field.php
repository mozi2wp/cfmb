<?php
/**
 * Shared behaviour for field types.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults for a single-value, text-like field. Types override what differs.
 */
abstract class Abstract_Field implements Field_Type {

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'basic';
	}

	/**
	 * No type-specific settings by default.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array();
	}

	/**
	 * Sanitize settings according to settings_schema(). Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $settings ): array {
		$clean = array();

		foreach ( $this->settings_schema() as $setting ) {
			$name    = $setting['name'];
			$default = $setting['default'] ?? '';
			$raw     = array_key_exists( $name, $settings ) ? $settings[ $name ] : $default;

			$clean[ $name ] = $this->sanitize_setting( $raw, $setting );
		}

		return $clean;
	}

	/**
	 * Values are stored by default.
	 *
	 * @return bool
	 */
	public function stores_value(): bool {
		return true;
	}

	/**
	 * Single value by default.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return false;
	}

	/**
	 * The default_value setting when the type has one.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return $field->setting( 'default_value', '' );
	}

	/**
	 * Plain single-line text.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Valid by default.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		return null;
	}

	/**
	 * Empty string, null, false or an empty array.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		if ( is_array( $value ) ) {
			return array() === array_filter( $value, static fn( $item ) => '' !== $item && null !== $item );
		}

		return null === $value || '' === $value || false === $value;
	}

	/**
	 * Stored value unchanged.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		return $value;
	}

	/**
	 * Stored value unchanged.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function editor_value( $value, Field $field ) {
		return $value;
	}

	/**
	 * Escaped text; lists are joined with commas.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		if ( is_array( $formatted ) ) {
			$items = array_filter( array_map( array( $this, 'scalar_text' ), $formatted ), 'strlen' );

			return esc_html( implode( wp_get_list_item_separator(), $items ) );
		}

		return esc_html( $this->scalar_text( $formatted ) );
	}

	/**
	 * Type description for the builder and edit screens.
	 *
	 * @return array<string, mixed>
	 */
	public function client_config(): array {
		return array(
			'name'        => $this->name(),
			'label'       => $this->label(),
			'category'    => $this->category(),
			'storesValue' => $this->stores_value(),
			'settings'    => $this->settings_schema(),
		);
	}

	/**
	 * Sanitize one setting by its control type.
	 *
	 * @param mixed                $raw     Raw value.
	 * @param array<string, mixed> $setting Setting descriptor.
	 * @return mixed
	 */
	protected function sanitize_setting( $raw, array $setting ) {
		$default = $setting['default'] ?? '';

		switch ( $setting['control'] ) {
			case 'toggle':
				return (bool) $raw;

			case 'number':
				return is_numeric( $raw ) ? $raw + 0 : ( is_numeric( $default ) ? $default + 0 : '' );

			case 'select':
				$options = array_keys( $setting['options'] ?? array() );

				return in_array( (string) $raw, array_map( 'strval', $options ), true ) ? (string) $raw : $default;

			case 'choices':
				return Choices::sanitize( $raw );

			case 'checkboxes':
				$options = array_map( 'strval', array_keys( $setting['options'] ?? array() ) );
				$picked  = array_map( static fn( $item ) => is_scalar( $item ) ? (string) $item : '', (array) $raw );

				return array_values( array_intersect( $options, $picked ) );

			case 'keys':
				$keys = array_map( static fn( $item ) => is_scalar( $item ) ? sanitize_key( (string) $item ) : '', (array) $raw );

				return array_values( array_unique( array_filter( $keys ) ) );

			case 'html':
				return is_scalar( $raw ) ? wp_kses_post( (string) $raw ) : '';

			case 'textarea':
				return is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';

			default:
				return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
		}
	}

	/**
	 * Text form of a scalar for display.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function scalar_text( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'custom-fields-meta-box' ) : __( 'No', 'custom-fields-meta-box' );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Setting descriptor helper.
	 *
	 * @param string               $name    Setting name.
	 * @param string               $label   Label.
	 * @param string               $control Control type.
	 * @param mixed                $default Default value.
	 * @param array<string, mixed> $extra   options, help, advanced.
	 * @return array<string, mixed>
	 */
	protected function setting( string $name, string $label, string $control, $default = '', array $extra = array() ): array {
		return array_merge(
			array(
				'name'    => $name,
				'label'   => $label,
				'control' => $control,
				'default' => $default,
			),
			$extra
		);
	}
}
