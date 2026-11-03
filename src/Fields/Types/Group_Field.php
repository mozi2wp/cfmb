<?php
/**
 * Group field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Structure_Base;
use CFMB\Fields\Structure_Field;

defined( 'ABSPATH' ) || exit;

/**
 * A set of sub-fields shown together. Values are stored as {group}_{sub},
 * for example hero_title, and templates get them as one array.
 */
class Group_Field extends Structure_Base {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'group';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Group', 'custom-fields-meta-box' );
	}

	/**
	 * Shape.
	 *
	 * @return string
	 */
	public function structure(): string {
		return Structure_Field::GROUP;
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting(
				'display',
				__( 'Display', 'custom-fields-meta-box' ),
				'select',
				'box',
				array(
					'options' => array(
						'box'   => __( 'In a box', 'custom-fields-meta-box' ),
						'plain' => __( 'Plain, like other fields', 'custom-fields-meta-box' ),
					),
				)
			),
		);
	}

	/**
	 * A required group needs at least one value inside.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return self::all_empty( $value );
	}
}
