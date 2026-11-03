<?php
/**
 * WordPress icons library.
 *
 * @package CFMB
 */

namespace CFMB\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * The SVG icons of the block editor (@wordpress/icons, GPL-2.0-or-later),
 * bundled as assets/icons/wordpress.json so they render without JavaScript.
 */
class WordPress_Icons implements Icon_Library {

	/**
	 * SVG markup by name, once read.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $svgs = null;

	/**
	 * Library ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'wp';
	}

	/**
	 * Name shown in the picker.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'WordPress icons', 'custom-fields-meta-box' );
	}

	/**
	 * Every icon.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function icons(): array {
		$icons = array();

		foreach ( $this->svgs() as $name => $svg ) {
			$icons[ $name ] = array(
				'label' => str_replace( '-', ' ', $name ),
				'svg'   => $svg,
			);
		}

		return $icons;
	}

	/**
	 * Whether an icon exists.
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->svgs()[ $name ] );
	}

	/**
	 * The SVG markup, marked decorative.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public function render( string $name ): string {
		$svg = $this->svgs()[ $name ] ?? '';

		if ( '' === $svg ) {
			return '';
		}

		// Our own bundled file; the markup was generated from @wordpress/icons at build time.
		return (string) preg_replace( '/^<svg /', '<svg class="cfmb-icon" aria-hidden="true" focusable="false" ', $svg );
	}

	/**
	 * Read the bundled file.
	 *
	 * @return array<string, string>
	 */
	private function svgs(): array {
		if ( null !== $this->svgs ) {
			return $this->svgs;
		}

		$file       = CFMB_DIR . 'assets/icons/wordpress.json';
		$data       = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
		$this->svgs = is_array( $data ) ? array_map( 'strval', $data ) : array();

		return $this->svgs;
	}
}
