<?php
/**
 * Gallery field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Several images in a chosen order. Stored as one meta row per attachment
 * ID, in order.
 */
class Gallery_Field extends Image_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'gallery';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Gallery', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array_merge(
			parent::settings_schema(),
			array(
				$this->setting( 'min', __( 'Minimum images', 'cfmb' ), 'number', '', array( 'advanced' => true ) ),
				$this->setting( 'max', __( 'Maximum images', 'cfmb' ), 'number', '', array( 'advanced' => true ) ),
			)
		);
	}

	/**
	 * A list of IDs.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return true;
	}

	/**
	 * No images by default.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return array();
	}

	/**
	 * Unique attachment IDs, in the given order.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$ids = array();

		foreach ( is_array( $value ) ? $value : array() as $item ) {
			$id = parent::sanitize_value( $item, $field );

			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Every item must be an image of an allowed type; counts within limits.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$ids = (array) $value;
		$min = (int) $field->setting( 'min', 0 );
		$max = (int) $field->setting( 'max', 0 );

		if ( $min > 0 && count( $ids ) < $min ) {
			/* translators: %d: minimum number of images */
			return sprintf( _n( 'Add at least %d image.', 'Add at least %d images.', $min, 'cfmb' ), $min );
		}

		if ( $max > 0 && count( $ids ) > $max ) {
			/* translators: %d: maximum number of images */
			return sprintf( _n( 'Use %d image or fewer.', 'Use %d images or fewer.', $max, 'cfmb' ), $max );
		}

		foreach ( $ids as $id ) {
			$error = parent::validate_value( $id, $field );

			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	/**
	 * Empty when there are no images.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return array() === array_filter( (array) $value );
	}

	/**
	 * List of details arrays, URLs or IDs.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$items = array();

		foreach ( (array) $value as $id ) {
			$item = parent::format_value( $id, $field );

			if ( null !== $item && '' !== $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Previews for the edit screen.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function editor_value( $value, Field $field ) {
		return array_values( array_filter( array_map( fn( $id ) => parent::editor_value( $id, $field ), (array) $value ) ) );
	}

	/**
	 * Images at the preview size.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$html = '';

		foreach ( (array) $formatted as $item ) {
			$html .= parent::display( $item, $field );
		}

		return $html;
	}
}
