<?php
/**
 * Icon field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Icons\Icon_Library_Registry;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * An icon from a library, the media library or a URL. Stored as one string,
 * "{source}:{value}": dashicons:admin-home, wp:star, media:123, url:https://…
 */
class Icon_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'icon';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Icon', 'custom-fields-meta-box' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'picker';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting(
				'sources',
				__( 'Icon sources', 'custom-fields-meta-box' ),
				'checkboxes',
				array(),
				array(
					'options' => $this->source_options(),
					'help'    => __( 'Leave all unchecked to offer every source.', 'custom-fields-meta-box' ),
				)
			),
			$this->setting(
				'return_format',
				__( 'Template value', 'custom-fields-meta-box' ),
				'select',
				'html',
				array(
					'advanced' => true,
					'options'  => array(
						'html'   => __( 'Icon HTML', 'custom-fields-meta-box' ),
						'string' => __( 'Stored value, like dashicons:star-filled', 'custom-fields-meta-box' ),
						'array'  => __( 'Source, name and HTML', 'custom-fields-meta-box' ),
					),
				)
			),
		);
	}

	/**
	 * No icon by default.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return '';
	}

	/**
	 * Normalize to "{source}:{value}". Unknown values are kept so validation can explain.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		if ( is_array( $value ) && isset( $value['value'] ) ) {
			$value = $value['value'];
		}

		$text = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $text || false === strpos( $text, ':' ) ) {
			return sanitize_text_field( $text );
		}

		list( $source, $name ) = explode( ':', $text, 2 );
		$source                = sanitize_key( $source );

		if ( 'url' === $source ) {
			$url = esc_url_raw( $name, array( 'http', 'https' ) );

			return '' === $url ? '' : 'url:' . $url;
		}

		if ( 'media' === $source ) {
			return 'media:' . absint( $name );
		}

		return $source . ':' . sanitize_text_field( $name );
	}

	/**
	 * The icon must exist and come from an allowed source.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$parsed = self::parse( (string) $value );

		if ( ! $parsed ) {
			return __( 'Choose an icon from the list.', 'custom-fields-meta-box' );
		}

		$allowed = array_map( 'strval', (array) $field->setting( 'sources', array() ) );
		if ( $allowed && ! in_array( $parsed[0], $allowed, true ) ) {
			return __( 'Icons from this source are not allowed here.', 'custom-fields-meta-box' );
		}

		if ( 'media' === $parsed[0] ) {
			return wp_attachment_is_image( (int) $parsed[1] ) ? null : __( 'Choose an image from the media library.', 'custom-fields-meta-box' );
		}

		if ( 'url' === $parsed[0] ) {
			return null;
		}

		$library = $this->libraries()->get( $parsed[0] );

		return $library && $library->has( $parsed[1] ) ? null : __( 'That icon is no longer available. Choose another one.', 'custom-fields-meta-box' );
	}

	/**
	 * HTML, the stored string, or details.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$parsed = self::parse( (string) $value );

		if ( ! $parsed ) {
			return null;
		}

		switch ( $field->setting( 'return_format', 'html' ) ) {
			case 'string':
				return (string) $value;
			case 'array':
				return array(
					'source' => $parsed[0],
					'name'   => $parsed[1],
					'html'   => $this->render( (string) $value ),
					'url'    => $this->url( (string) $value ),
				);
			default:
				return $this->render( (string) $value );
		}
	}

	/**
	 * Value plus what the picker needs to preview it.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function editor_value( $value, Field $field ) {
		$parsed = self::parse( (string) $value );

		if ( ! $parsed ) {
			return null;
		}

		return array(
			'value'   => (string) $value,
			'preview' => $this->preview( $parsed[0], $parsed[1] ),
		);
	}

	/**
	 * Icon HTML.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		if ( is_array( $formatted ) ) {
			return (string) ( $formatted['html'] ?? '' );
		}

		$text = (string) $formatted;

		return self::parse( $text ) ? $this->render( $text ) : $text;
	}

	/**
	 * HTML for a stored value.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public function render( string $value ): string {
		$parsed = self::parse( $value );

		if ( ! $parsed ) {
			return '';
		}

		if ( 'media' === $parsed[0] || 'url' === $parsed[0] ) {
			$url = $this->url( $value );

			return '' === $url ? '' : sprintf( '<img class="cfmb-icon" src="%s" alt="" />', esc_url( $url ) );
		}

		$library = $this->libraries()->get( $parsed[0] );

		return $library && $library->has( $parsed[1] ) ? $library->render( $parsed[1] ) : '';
	}

	/**
	 * Preview data for the picker: kind (class, svg, img) and src.
	 *
	 * @param string $source Source.
	 * @param string $name   Icon name or ID.
	 * @return array<string, string>
	 */
	public function preview( string $source, string $name ): array {
		if ( 'media' === $source || 'url' === $source ) {
			return array(
				'kind' => 'img',
				'src'  => $this->url( $source . ':' . $name ),
			);
		}

		$library = $this->libraries()->get( $source );
		$icon    = $library ? ( $library->icons()[ $name ] ?? array() ) : array();

		if ( isset( $icon['svg'] ) ) {
			return array(
				'kind' => 'svg',
				'src'  => $icon['svg'],
			);
		}

		return array(
			'kind' => 'class',
			'src'  => (string) ( $icon['class'] ?? '' ),
		);
	}

	/**
	 * Split a stored value into source and name.
	 *
	 * @param string $value Stored value.
	 * @return array{0: string, 1: string}|null
	 */
	public static function parse( string $value ): ?array {
		if ( ! preg_match( '/^([a-z0-9_-]+):(.+)$/', $value, $parts ) ) {
			return null;
		}

		return array( $parts[1], $parts[2] );
	}

	/**
	 * Image URL for media and URL icons.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	private function url( string $value ): string {
		$parsed = self::parse( $value );

		if ( ! $parsed ) {
			return '';
		}

		if ( 'url' === $parsed[0] ) {
			return $parsed[1];
		}

		if ( 'media' === $parsed[0] ) {
			$src = wp_get_attachment_image_url( (int) $parsed[1], 'thumbnail' );

			return $src ? $src : (string) wp_get_attachment_url( (int) $parsed[1] );
		}

		return '';
	}

	/**
	 * Source choices for settings.
	 *
	 * @return array<string, string>
	 */
	private function source_options(): array {
		$options = array();

		foreach ( $this->libraries()->all() as $id => $library ) {
			$options[ $id ] = $library->label();
		}

		$options['media'] = __( 'Media library', 'custom-fields-meta-box' );
		$options['url']   = __( 'Image URL', 'custom-fields-meta-box' );

		return $options;
	}

	/**
	 * The icon library registry.
	 *
	 * @return Icon_Library_Registry
	 */
	private function libraries(): Icon_Library_Registry {
		return cfmb()->get( Icon_Library_Registry::class );
	}
}
