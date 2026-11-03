<?php
/**
 * Relationship field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * An ordered list of posts, chosen from a searchable list next to it.
 * Stored as one meta row per post ID, in order.
 */
class Relationship_Field extends Post_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'relationship';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Relationship', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->post_type_setting(),
			$this->setting( 'min', __( 'Minimum posts', 'cfmb' ), 'number', '' ),
			$this->setting( 'max', __( 'Maximum posts', 'cfmb' ), 'number', '' ),
			$this->setting(
				'filters',
				__( 'Filters', 'cfmb' ),
				'checkboxes',
				array( 'search', 'post_type' ),
				array(
					'options' => array(
						'search'    => __( 'Search box', 'cfmb' ),
						'post_type' => __( 'Post type menu', 'cfmb' ),
						'taxonomy'  => __( 'Category and tag menu', 'cfmb' ),
					),
				)
			),
			$this->return_format_setting(),
		);
	}

	/**
	 * Always a list.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return true;
	}

	/**
	 * Type check plus the number of posts.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$count = count( $this->as_list( $value ) );
		$min   = (int) $field->setting( 'min', 0 );
		$max   = (int) $field->setting( 'max', 0 );

		if ( $min > 0 && $count < $min ) {
			/* translators: %d: minimum number of posts */
			return sprintf( _n( 'Choose at least %d post.', 'Choose at least %d posts.', $min, 'cfmb' ), $min );
		}

		if ( $max > 0 && $count > $max ) {
			/* translators: %d: maximum number of posts */
			return sprintf( _n( 'Choose %d post or fewer.', 'Choose %d posts or fewer.', $max, 'cfmb' ), $max );
		}

		return parent::validate_value( $value, $field );
	}
}
