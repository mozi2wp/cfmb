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
		return __( 'Relationship', 'custom-fields-meta-box' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->post_type_setting(),
			$this->setting( 'min', __( 'Minimum posts', 'custom-fields-meta-box' ), 'number', '' ),
			$this->setting( 'max', __( 'Maximum posts', 'custom-fields-meta-box' ), 'number', '' ),
			$this->setting(
				'filters',
				__( 'Filters', 'custom-fields-meta-box' ),
				'checkboxes',
				array( 'search', 'post_type' ),
				array(
					'options' => array(
						'search'    => __( 'Search box', 'custom-fields-meta-box' ),
						'post_type' => __( 'Post type menu', 'custom-fields-meta-box' ),
						'taxonomy'  => __( 'Category and tag menu', 'custom-fields-meta-box' ),
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
			return sprintf( _n( 'Choose at least %d post.', 'Choose at least %d posts.', $min, 'custom-fields-meta-box' ), $min );
		}

		if ( $max > 0 && $count > $max ) {
			/* translators: %d: maximum number of posts */
			return sprintf( _n( 'Choose %d post or fewer.', 'Choose %d posts or fewer.', $max, 'custom-fields-meta-box' ), $max );
		}

		return parent::validate_value( $value, $field );
	}
}
