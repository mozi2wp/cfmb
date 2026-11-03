<?php
/**
 * Font Awesome Free icons.
 *
 * @package CFMB
 */

namespace CFMB\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Font Awesome Free (solid, regular and brands), bundled as SVG markup in
 * assets/icons/fontawesome.json, so no font or CDN is loaded. Names are
 * "{style}-{icon}", e.g. "solid-house"; values are stored as "fa:solid-house".
 *
 * Icons: CC BY 4.0, by Fonticons, Inc. (https://fontawesome.com).
 */
class Font_Awesome_Icons implements Icon_Library {

	/**
	 * Icons by name, on first use.
	 *
	 * @var array<string, array{label: string, svg: string}>|null
	 */
	private ?array $icons = null;

	/**
	 * Path of the bundled data file.
	 *
	 * @return string
	 */
	public static function file(): string {
		return CFMB_DIR . 'assets/icons/fontawesome.json';
	}

	/**
	 * Library ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'fa';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Font Awesome', 'cfmb' );
	}

	/**
	 * Every icon with its label and SVG.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function icons(): array {
		return $this->load();
	}

	/**
	 * Whether an icon exists.
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->load()[ $name ] );
	}

	/**
	 * Inline SVG for an icon.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public function render( string $name ): string {
		$svg = (string) ( $this->load()[ $name ]['svg'] ?? '' );

		if ( '' === $svg ) {
			return '';
		}

		// Our own bundled file, built from the Font Awesome Free package by bin/build-icons.js.
		return (string) preg_replace( '/^<svg /', '<svg class="cfmb-icon cfmb-icon--fa" aria-hidden="true" focusable="false" ', $svg );
	}

	/**
	 * Read the data file once.
	 *
	 * @return array<string, array{label: string, svg: string}>
	 */
	private function load(): array {
		if ( null !== $this->icons ) {
			return $this->icons;
		}

		$file = self::file();
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.

		$this->icons = array();

		foreach ( is_array( $data ) ? $data : array() as $name => $icon ) {
			if ( is_array( $icon ) && isset( $icon['svg'] ) && 0 === strpos( (string) $icon['svg'], '<svg ' ) ) {
				$this->icons[ (string) $name ] = array(
					'label' => (string) ( $icon['label'] ?? $name ),
					'svg'   => (string) $icon['svg'],
				);
			}
		}

		return $this->icons;
	}
}
