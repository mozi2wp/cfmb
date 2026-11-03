<?php
/**
 * Elementor tag: a Color field.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Core\DynamicTags\Data_Tag;

defined( 'ABSPATH' ) || exit;

/**
 * The stored color, for color controls.
 */
class Color_Tag extends Data_Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-color';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB Color', 'custom-fields-meta-box' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'color' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'color';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->add_field_controls();
	}

	/**
	 * The color. It ends up in CSS, so only hex and rgb()/rgba() pass.
	 *
	 * @param array<string, mixed> $options Elementor options.
	 * @return string
	 */
	public function get_value( array $options = array() ) {
		$color = $this->tag_values()->color( $this->field_path(), $this->field_object() );

		return 1 === preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*[\d.,\s%]+\))$/', $color ) ? $color : '';
	}
}
