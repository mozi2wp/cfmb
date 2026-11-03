<?php
/**
 * Link field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Fields\Compound_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A URL with link text and an "open in a new tab" choice. Each part is
 * stored on its own: cta_url, cta_title, cta_target.
 */
class Link_Field extends Abstract_Field implements Compound_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'link';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Link', 'custom-fields-meta-box' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'relational';
	}

	/**
	 * Stored parts.
	 *
	 * @return string[]
	 */
	public function parts(): array {
		return array( 'url', 'title', 'target' );
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
				__( 'Template value', 'custom-fields-meta-box' ),
				'select',
				'array',
				array(
					'options' => array(
						'array' => __( 'URL, text and target', 'custom-fields-meta-box' ),
						'url'   => __( 'URL only', 'custom-fields-meta-box' ),
					),
				)
			),
		);
	}

	/**
	 * Empty link.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return array(
			'url'    => '',
			'title'  => '',
			'target' => '',
		);
	}

	/**
	 * Clean each part.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$value = is_array( $value ) ? $value : array( 'url' => $value );
		$url   = is_scalar( $value['url'] ?? null ) ? trim( (string) $value['url'] ) : '';

		return array(
			'url'    => '' === $url ? '' : esc_url_raw( $url ),
			'title'  => is_scalar( $value['title'] ?? null ) ? sanitize_text_field( (string) $value['title'] ) : '',
			'target' => '_blank' === ( $value['target'] ?? '' ) || true === ( $value['target'] ?? null ) ? '_blank' : '',
		);
	}

	/**
	 * Text without a URL is a mistake worth pointing out.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( is_array( $value ) && '' === ( $value['url'] ?? '' ) && '' !== ( $value['title'] ?? '' ) ) {
			return __( 'Add the address the link points to.', 'custom-fields-meta-box' );
		}

		return null;
	}

	/**
	 * Empty without a URL and text.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return ! is_array( $value ) || ( '' === (string) ( $value['url'] ?? '' ) && '' === (string) ( $value['title'] ?? '' ) );
	}

	/**
	 * Array or URL.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		if ( $this->is_empty( $value ) ) {
			return null;
		}

		$link = array(
			'url'    => (string) ( $value['url'] ?? '' ),
			'title'  => (string) ( $value['title'] ?? '' ),
			'target' => (string) ( $value['target'] ?? '' ),
		);

		return 'url' === $field->setting( 'return_format', 'array' ) ? $link['url'] : $link;
	}

	/**
	 * An anchor tag.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$link = is_array( $formatted ) ? $formatted : array( 'url' => (string) $formatted );
		$url  = (string) ( $link['url'] ?? '' );

		if ( '' === $url ) {
			return '';
		}

		$text   = '' !== (string) ( $link['title'] ?? '' ) ? (string) $link['title'] : $url;
		$target = '_blank' === ( $link['target'] ?? '' ) ? ' target="_blank" rel="noopener"' : '';

		return sprintf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( $url ), $target, esc_html( $text ) );
	}
}
