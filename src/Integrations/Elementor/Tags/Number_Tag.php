<?php
/**
 * Elementor tag: a Number or Range field.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Core\DynamicTags\Tag;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the number as stored, for number controls such as counters.
 */
class Number_Tag extends Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-number';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB Number', 'cfmb' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'number', 'text' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'number';
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
	 * Print the value.
	 *
	 * @return void
	 */
	public function render() {
		echo esc_html( $this->tag_values()->number( $this->field_path(), $this->field_object() ) );
	}
}
