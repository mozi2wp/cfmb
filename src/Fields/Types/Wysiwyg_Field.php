<?php
/**
 * WYSIWYG editor field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Rich text edited with the classic WordPress editor (TinyMCE + Text tab).
 */
class Wysiwyg_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'wysiwyg';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Rich text editor', 'custom-fields-meta-box' );
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
			$this->setting( 'default_value', __( 'Default value', 'custom-fields-meta-box' ), 'html' ),
			$this->setting(
				'tabs',
				__( 'Editor tabs', 'custom-fields-meta-box' ),
				'select',
				'all',
				array(
					'options' => array(
						'all'    => __( 'Visual and Text', 'custom-fields-meta-box' ),
						'visual' => __( 'Visual only', 'custom-fields-meta-box' ),
						'text'   => __( 'Text only', 'custom-fields-meta-box' ),
					),
				)
			),
			$this->setting(
				'toolbar',
				__( 'Toolbar', 'custom-fields-meta-box' ),
				'select',
				'full',
				array(
					'options' => array(
						'full'  => __( 'Full', 'custom-fields-meta-box' ),
						'basic' => __( 'Basic', 'custom-fields-meta-box' ),
					),
				)
			),
			$this->setting( 'media_upload', __( 'Show "Add Media" button', 'custom-fields-meta-box' ), 'toggle', true ),
			$this->setting( 'rows', __( 'Height in rows', 'custom-fields-meta-box' ), 'number', 10, array( 'advanced' => true ) ),
		);
	}

	/**
	 * Same rule as post content: users who may post unfiltered HTML keep it.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
	}

	/**
	 * Paragraphs and shortcodes, as post content would get them.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		return do_shortcode( shortcode_unautop( wpautop( wptexturize( $value ) ) ) );
	}

	/**
	 * HTML limited to what posts allow.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		return wp_kses_post( $this->scalar_text( $formatted ) );
	}
}
