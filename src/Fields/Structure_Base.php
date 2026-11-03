<?php
/**
 * Shared behaviour for Group, Repeater and Stacks.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Field_Values and Row_Values do the walking; these types only describe
 * their settings and how an empty value looks.
 */
abstract class Structure_Base extends Abstract_Field implements Structure_Field {

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'structure';
	}

	/**
	 * Sub-values are sanitized one by one in Row_Values.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		return is_array( $value ) ? $value : array();
	}

	/**
	 * No default of its own; sub-fields have theirs.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return array();
	}

	/**
	 * Nothing to print directly. Templates loop with cfmb_have_rows().
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		return '';
	}

	/**
	 * Settings shared by Repeater and Stacks.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function row_settings(): array {
		return array(
			$this->setting( 'min', __( 'Minimum rows', 'cfmb' ), 'number', '', array( 'help' => __( 'Checked when the post is published.', 'cfmb' ) ) ),
			$this->setting( 'max', __( 'Maximum rows', 'cfmb' ), 'number', '' ),
			$this->setting( 'button_label', __( 'Add button text', 'cfmb' ), 'text', '', array( 'help' => __( 'Leave empty for "Add row".', 'cfmb' ) ) ),
			$this->setting( 'collapsed', __( 'Start with rows collapsed', 'cfmb' ), 'toggle', false, array( 'help' => __( 'Collapsed rows load their fields only when opened, which keeps long lists fast.', 'cfmb' ) ) ),
			$this->setting(
				'pagination',
				__( 'Show rows in pages', 'cfmb' ),
				'toggle',
				false,
				array(
					'advanced' => true,
					'help'     => __( 'For very long lists. Only the pages you open are loaded and saved. Works on top-level fields; inside other rows all rows show at once.', 'cfmb' ),
				)
			),
			$this->setting( 'per_page', __( 'Rows per page', 'cfmb' ), 'number', 20, array( 'advanced' => true ) ),
		);
	}

	/**
	 * Sanitize settings; row limits can't be negative.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $settings ): array {
		$clean = parent::sanitize_settings( $settings );

		foreach ( array( 'min', 'max' ) as $limit ) {
			if ( array_key_exists( $limit, $clean ) ) {
				$clean[ $limit ] = is_numeric( $clean[ $limit ] ) && $clean[ $limit ] > 0 ? (int) $clean[ $limit ] : '';
			}
		}

		if ( array_key_exists( 'per_page', $clean ) ) {
			$clean['per_page'] = max( 1, min( 200, (int) $clean['per_page'] ) );
		}

		return $clean;
	}

	/**
	 * Whether every value inside is empty.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	protected static function all_empty( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( '_layout' !== $key && ! self::all_empty( $item ) ) {
					return false;
				}
			}

			return true;
		}

		return null === $value || '' === $value || false === $value;
	}
}
