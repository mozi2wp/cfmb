<?php
/**
 * Tab field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Starts a tab. Fields after it, up to the next tab, appear inside it.
 */
class Tab_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'tab';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Tab', 'custom-fields-meta-box' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'layout';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting(
				'placement',
				__( 'Tab position', 'custom-fields-meta-box' ),
				'select',
				'top',
				array(
					'options' => array(
						'top'  => __( 'Top', 'custom-fields-meta-box' ),
						'left' => __( 'Left', 'custom-fields-meta-box' ),
					),
					'help'    => __( 'The first tab in a group sets the position for all its tabs.', 'custom-fields-meta-box' ),
				)
			),
		);
	}

	/**
	 * Nothing is saved.
	 *
	 * @return bool
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * No default.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return null;
	}
}
