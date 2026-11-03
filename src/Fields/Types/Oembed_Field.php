<?php
/**
 * oEmbed field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A link to a video, post or other embeddable page. Stores the URL; the
 * embed HTML comes from WordPress's own oEmbed handling and cache.
 */
class Oembed_Field extends Url_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'oembed';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'oEmbed', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'content';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting(
				'return_format',
				__( 'Template value', 'cfmb' ),
				'select',
				'html',
				array(
					'options' => array(
						'html' => __( 'Embed HTML', 'cfmb' ),
						'url'  => __( 'URL', 'cfmb' ),
					),
				)
			),
			$this->setting( 'width', __( 'Embed width', 'cfmb' ), 'number', '', array( 'advanced' => true ) ),
			$this->setting( 'height', __( 'Embed height', 'cfmb' ), 'number', '', array( 'advanced' => true ) ),
		);
	}

	/**
	 * No default URL.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return '';
	}

	/**
	 * Embed HTML or the URL.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$url = is_string( $value ) ? $value : '';

		if ( '' === $url ) {
			return null;
		}

		return 'url' === $field->setting( 'return_format', 'html' ) ? $url : $this->embed( $url, $field );
	}

	/**
	 * Embed HTML. Providers' HTML is filtered by WordPress before it is cached.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		if ( ! is_string( $formatted ) || '' === $formatted ) {
			return '';
		}

		return 0 === strpos( $formatted, 'http' ) ? $this->embed( $formatted, $field ) : $formatted;
	}

	/**
	 * Embed through WP_Embed, which caches results (in post meta inside the
	 * loop, otherwise in the oembed_cache post type).
	 *
	 * @param string $url   URL.
	 * @param Field  $field Field definition.
	 * @return string
	 */
	private function embed( string $url, Field $field ): string {
		global $wp_embed;

		$attributes = array_filter(
			array(
				'width'  => (int) $field->setting( 'width', 0 ),
				'height' => (int) $field->setting( 'height', 0 ),
			)
		);

		if ( $wp_embed instanceof \WP_Embed ) {
			$html = $wp_embed->shortcode( $attributes, $url );

			return is_string( $html ) ? $html : '';
		}

		$html = wp_oembed_get( $url, $attributes );

		return is_string( $html ) ? $html : sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $url ) );
	}
}
