<?php
/**
 * Elementor tag: a field as text or HTML.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the same escaped HTML as the [cfmb] shortcode.
 */
class Field_Tag extends Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-field';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB Field', 'cfmb' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'text' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'text';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->add_field_controls();

		$this->add_control(
			'separator',
			array(
				'label'       => __( 'Separator between items', 'cfmb' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'For fields with several values. Leave empty for the default.', 'cfmb' ),
			)
		);

		$this->add_control(
			'size',
			array(
				'label'   => __( 'Image size', 'cfmb' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
	}

	/**
	 * Print the value.
	 *
	 * @return void
	 */
	public function render() {
		$args = array(
			'size'      => (string) $this->get_settings( 'size' ),
			'separator' => (string) $this->get_settings( 'separator' ),
		);

		echo $this->tag_values()->html( $this->field_path(), $this->field_object(), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Formatter.
	}
}
