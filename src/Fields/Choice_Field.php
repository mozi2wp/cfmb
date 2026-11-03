<?php
/**
 * Shared behaviour for fields with a fixed list of choices.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Base for select, checkbox, radio and button group.
 */
abstract class Choice_Field extends Abstract_Field {

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'choice';
	}

	/**
	 * Default value: one choice, or one per line for multi-value fields.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		$text = (string) $field->setting( 'default_value', '' );

		if ( ! $this->is_multiple( $field ) ) {
			return trim( $text );
		}

		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ?: array() ), 'strlen' ) );
	}

	/**
	 * Keep only known choices.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$allowed = Choices::values( (array) $field->setting( 'choices', array() ) );

		if ( $this->is_multiple( $field ) ) {
			$values = is_array( $value ) ? $value : ( '' === $value || null === $value ? array() : array( $value ) );
			$values = array_map( static fn( $item ) => is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '', $values );

			return array_values( array_unique( array_intersect( $values, $allowed ) ) );
		}

		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Value, label or both, per the return format setting.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$choices = (array) $field->setting( 'choices', array() );
		$format  = (string) $field->setting( 'return_format', 'value' );

		$convert = static function ( $item ) use ( $choices, $format ) {
			$item = (string) $item;

			if ( 'label' === $format ) {
				return Choices::label_for( $choices, $item );
			}

			if ( 'array' === $format ) {
				return array(
					'value' => $item,
					'label' => Choices::label_for( $choices, $item ),
				);
			}

			return $item;
		};

		if ( is_array( $value ) ) {
			return array_map( $convert, $value );
		}

		return '' === $value || null === $value ? null : $convert( $value );
	}

	/**
	 * Labels, comma-separated.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$choices = (array) $field->setting( 'choices', array() );
		$items   = is_array( $formatted ) && ! isset( $formatted['value'] ) ? $formatted : array( $formatted );
		$labels  = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$labels[] = (string) ( $item['label'] ?? '' );
			} elseif ( null !== $item ) {
				$labels[] = Choices::label_for( $choices, (string) $item );
			}
		}

		return esc_html( implode( wp_get_list_item_separator(), array_filter( $labels, 'strlen' ) ) );
	}

	/**
	 * The choices list setting.
	 *
	 * @return array<string, mixed>
	 */
	protected function choices_setting(): array {
		return $this->setting(
			'choices',
			__( 'Choices', 'cfmb' ),
			'choices',
			array(),
			array( 'help' => __( 'One choice per line. Use "value : Label" to store a different value than the label people see.', 'cfmb' ) )
		);
	}

	/**
	 * The default value setting.
	 *
	 * @param bool $multiple Whether several defaults are allowed.
	 * @return array<string, mixed>
	 */
	protected function default_setting( bool $multiple ): array {
		return $multiple
			? $this->setting( 'default_value', __( 'Default values', 'cfmb' ), 'textarea', '', array( 'help' => __( 'One value per line.', 'cfmb' ) ) )
			: $this->setting( 'default_value', __( 'Default value', 'cfmb' ), 'text' );
	}

	/**
	 * The return format setting.
	 *
	 * @return array<string, mixed>
	 */
	protected function return_format_setting(): array {
		return $this->setting(
			'return_format',
			__( 'Template value', 'cfmb' ),
			'select',
			'value',
			array(
				'advanced' => true,
				'options'  => array(
					'value' => __( 'Value', 'cfmb' ),
					'label' => __( 'Label', 'cfmb' ),
					'array' => __( 'Value and label', 'cfmb' ),
				),
			)
		);
	}

	/**
	 * The layout setting for lists of inputs.
	 *
	 * @return array<string, mixed>
	 */
	protected function layout_setting(): array {
		return $this->setting(
			'layout',
			__( 'Layout', 'cfmb' ),
			'select',
			'vertical',
			array(
				'options' => array(
					'vertical'   => __( 'Stacked', 'cfmb' ),
					'horizontal' => __( 'In a row', 'cfmb' ),
				),
			)
		);
	}
}
