<?php
/**
 * Accordion field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Starts a collapsible section. Fields after it, up to the next accordion,
 * tab or accordion end point, sit inside it. Saves nothing.
 */
class Accordion_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'accordion';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Accordion', 'custom-fields-meta-box' );
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
			$this->setting( 'open', __( 'Open at first', 'custom-fields-meta-box' ), 'toggle', false ),
			$this->setting(
				'endpoint',
				__( 'End point', 'custom-fields-meta-box' ),
				'toggle',
				false,
				array( 'help' => __( 'Closes the accordion above without starting a new one. Fields after it show normally.', 'custom-fields-meta-box' ) )
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
