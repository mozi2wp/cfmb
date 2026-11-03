<?php
/**
 * Elementor tag: an Image field, or the first image of a Gallery.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;

defined( 'ABSPATH' ) || exit;

/**
 * For image controls. Inside a Field Loop over a gallery, "Loop item: Image"
 * gives each image in turn.
 */
class Image_Tag extends Data_Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-image';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB Image', 'cfmb' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'image' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'image';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->add_field_controls();

		$this->add_control(
			'fallback',
			array(
				'label' => __( 'Fallback image', 'cfmb' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);
	}

	/**
	 * Image ID and URL, as image controls expect.
	 *
	 * @param array<string, mixed> $options Elementor options.
	 * @return array<string, mixed>
	 */
	public function get_value( array $options = array() ) {
		$image = $this->tag_values()->image( $this->field_path(), $this->field_object() );

		if ( $image ) {
			return array(
				'id'  => $image['id'],
				'url' => $image['url'],
			);
		}

		$fallback = $this->get_settings( 'fallback' );

		return is_array( $fallback ) ? $fallback : array();
	}
}
