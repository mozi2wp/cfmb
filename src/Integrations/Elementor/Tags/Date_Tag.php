<?php
/**
 * Elementor tag: a Date, Time or Date Time field in a chosen format.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the date in a PHP date format. For date controls (such as a
 * countdown) use the format Y-m-d H:i.
 */
class Date_Tag extends Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-date';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB Date', 'custom-fields-meta-box' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'text', 'datetime' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'date';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->add_field_controls();

		$this->add_control(
			'format',
			array(
				'label'       => __( 'Date format', 'custom-fields-meta-box' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'A PHP date format such as F j, Y. Leave empty to use the field\'s own format. Use Y-m-d H:i for date controls.', 'custom-fields-meta-box' ),
			)
		);
	}

	/**
	 * Print the value.
	 *
	 * @return void
	 */
	public function render() {
		echo esc_html( $this->tag_values()->date( $this->field_path(), $this->field_object(), (string) $this->get_settings( 'format' ) ) );
	}
}
