<?php
/**
 * Contract for field types.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A field type. Adding a type means adding one class that implements this
 * and registering it on `cfmb/fields/register`. Most types extend
 * Abstract_Field and override only what differs.
 */
interface Field_Type {

	/**
	 * Type key stored in field definitions, e.g. "text".
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * Label shown in the builder.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Builder category: basic, content, choice, relational, picker, layout, structure.
	 *
	 * @return string
	 */
	public function category(): string;

	/**
	 * Type-specific settings the builder shows.
	 *
	 * Each entry: name, label, control (text|textarea|html|number|toggle|select|choices|checkboxes|keys,
	 * plus builder-only controls such as subfield and preset_source that store a plain string),
	 * default, and optionally options, help, advanced.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array;

	/**
	 * Clean type-specific settings coming from the builder.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $settings ): array;

	/**
	 * Whether the type saves a value. Layout types such as tab do not.
	 *
	 * @return bool
	 */
	public function stores_value(): bool;

	/**
	 * Whether the value is a list stored as one meta row per item.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool;

	/**
	 * Value used when nothing is saved yet.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field );

	/**
	 * Clean a submitted value before it is stored.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field );

	/**
	 * Check a sanitized value. Returns an error message, or null when valid.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string;

	/**
	 * Whether a value counts as empty for the required check.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool;

	/**
	 * Convert a stored value into what templates receive.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field );

	/**
	 * Convert a stored value into what the edit screen UI needs,
	 * e.g. an image ID plus its preview URL.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function editor_value( $value, Field $field );

	/**
	 * Escaped HTML for a formatted value, used by cfmb_the_field().
	 *
	 * @param mixed $formatted Value from format_value().
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string;

	/**
	 * Everything the builder and edit screens need to know about the type.
	 *
	 * @return array<string, mixed>
	 */
	public function client_config(): array;
}
