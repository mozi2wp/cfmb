<?php
/**
 * Elementor tag: a Gallery field.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Core\DynamicTags\Data_Tag;

defined( 'ABSPATH' ) || exit;

/**
 * Images for gallery controls. An Image field gives a one-image gallery.
 */
class Gallery_Tag extends Data_Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-gallery';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB Gallery', 'cfmb' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'gallery' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'gallery';
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
	 * Images as id/url pairs.
	 *
	 * @param array<string, mixed> $options Elementor options.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_value( array $options = array() ) {
		$images = array();

		foreach ( $this->tag_values()->image_ids( $this->field_path(), $this->field_object() ) as $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );

			if ( $url ) {
				$images[] = array(
					'id'  => $id,
					'url' => $url,
				);
			}
		}

		return $images;
	}
}
