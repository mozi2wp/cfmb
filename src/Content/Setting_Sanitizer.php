<?php
/**
 * Sanitizes settings described by setting descriptors.
 *
 * @package CFMB
 */

namespace CFMB\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Cleans a settings array against a list of descriptors. The same
 * descriptors drive the builder forms, so what the form can send is
 * exactly what gets stored.
 *
 * Descriptor: name, label, control, default, and optionally options, help,
 * advanced, section. Controls: text, textarea, toggle, tristate, select,
 * checkboxes, number, slug, dashicon, labels.
 */
final class Setting_Sanitizer {

	/**
	 * Sanitize settings. Unknown keys are dropped; missing keys get their default.
	 *
	 * @param array<string, mixed>             $settings    Raw settings.
	 * @param array<int, array<string, mixed>> $descriptors Setting descriptors.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $settings, array $descriptors ): array {
		$clean = array();

		foreach ( $descriptors as $descriptor ) {
			$name = (string) $descriptor['name'];
			$raw  = array_key_exists( $name, $settings ) ? $settings[ $name ] : ( $descriptor['default'] ?? '' );

			$clean[ $name ] = self::sanitize_one( $raw, $descriptor );
		}

		return $clean;
	}

	/**
	 * Defaults from descriptors.
	 *
	 * @param array<int, array<string, mixed>> $descriptors Setting descriptors.
	 * @return array<string, mixed>
	 */
	public static function defaults( array $descriptors ): array {
		$defaults = array();

		foreach ( $descriptors as $descriptor ) {
			$defaults[ (string) $descriptor['name'] ] = $descriptor['default'] ?? '';
		}

		return $defaults;
	}

	/**
	 * Sanitize one value.
	 *
	 * @param mixed                $raw        Raw value.
	 * @param array<string, mixed> $descriptor Descriptor.
	 * @return mixed
	 */
	public static function sanitize_one( $raw, array $descriptor ) {
		$default = $descriptor['default'] ?? '';

		switch ( $descriptor['control'] ) {
			case 'toggle':
				return is_string( $raw ) ? ! in_array( strtolower( $raw ), array( '', '0', 'false', 'no' ), true ) : (bool) $raw;

			case 'tristate':
				// '' follows another setting (usually Public), '1' yes, '0' no.
				$raw = is_bool( $raw ) ? ( $raw ? '1' : '0' ) : ( is_scalar( $raw ) ? (string) $raw : '' );

				return in_array( $raw, array( '', '0', '1' ), true ) ? $raw : '';

			case 'number':
				if ( '' === $raw || null === $raw ) {
					return '';
				}

				return is_numeric( $raw ) ? (int) $raw : ( is_numeric( $default ) ? (int) $default : '' );

			case 'select':
				$options = array_map( 'strval', array_keys( (array) ( $descriptor['options'] ?? array() ) ) );
				$raw     = is_scalar( $raw ) ? (string) $raw : '';

				return in_array( $raw, $options, true ) ? $raw : (string) $default;

			case 'checkboxes':
				$picked = array_map( static fn( $item ) => is_scalar( $item ) ? sanitize_key( (string) $item ) : '', (array) $raw );
				$picked = array_values( array_unique( array_filter( $picked ) ) );

				// Lists of post types or taxonomies may name things registered later, so they are kept when free-form.
				if ( empty( $descriptor['free'] ) ) {
					$picked = array_values( array_intersect( $picked, array_map( 'strval', array_keys( (array) ( $descriptor['options'] ?? array() ) ) ) ) );
				}

				return $picked;

			case 'slug':
				return is_scalar( $raw ) ? trim( (string) preg_replace( '#[^a-z0-9_/-]+#', '-', strtolower( remove_accents( (string) $raw ) ) ), '/-' ) : '';

			case 'dashicon':
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';

				if ( 0 === strpos( $raw, 'data:image/svg+xml;base64,' ) ) {
					return 1 === preg_match( '#^data:image/svg\+xml;base64,[A-Za-z0-9+/=]+$#', $raw ) ? $raw : (string) $default;
				}

				if ( 0 === strpos( $raw, 'http://' ) || 0 === strpos( $raw, 'https://' ) ) {
					return esc_url_raw( $raw );
				}

				return 1 === preg_match( '/^dashicons-[a-z0-9-]+$/', $raw ) ? $raw : (string) $default;

			case 'labels':
				$labels = array();

				foreach ( array_keys( (array) ( $descriptor['labels'] ?? array() ) ) as $key ) {
					$value = is_array( $raw ) && isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';

					if ( '' !== $value ) {
						$labels[ $key ] = $value;
					}
				}

				return $labels;

			case 'textarea':
				return is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';

			default:
				return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
		}
	}
}
