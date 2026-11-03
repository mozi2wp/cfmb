<?php
/**
 * Elementor tag: a URL from a field.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;

defined( 'ABSPATH' ) || exit;

/**
 * The link of a Link or URL field, a file or image URL, the permalink of a
 * linked post or term, or a mailto: for an Email field.
 */
class Url_Tag extends Data_Tag {

	use Tag_Controls;

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-url';
	}

	/**
	 * Tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'CFMB URL', 'custom-fields-meta-box' );
	}

	/**
	 * Controls it can fill.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'url' );
	}

	/**
	 * Kind of value.
	 *
	 * @return string
	 */
	protected function field_kind(): string {
		return 'url';
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
				'label' => __( 'Fallback URL', 'custom-fields-meta-box' ),
				'type'  => Controls_Manager::TEXT,
			)
		);
	}

	/**
	 * The URL.
	 *
	 * @param array<string, mixed> $options Elementor options.
	 * @return string
	 */
	public function get_value( array $options = array() ) {
		$url = $this->tag_values()->url( $this->field_path(), $this->field_object() );

		return '' !== $url ? esc_url( $url ) : esc_url( (string) $this->get_settings( 'fallback' ) );
	}
}
