<?php
/**
 * Dashicons library.
 *
 * @package CFMB
 */

namespace CFMB\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * The icon font WordPress ships. The list is read from core's own
 * dashicons.css, so it always matches the installed version.
 */
class Dashicons implements Icon_Library {

	/**
	 * Icon names, once read.
	 *
	 * @var string[]|null
	 */
	private ?array $names = null;

	/**
	 * Library ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'dashicons';
	}

	/**
	 * Name shown in the picker.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Dashicons', 'custom-fields-meta-box' );
	}

	/**
	 * Every icon.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function icons(): array {
		$icons = array();

		foreach ( $this->names() as $name ) {
			$icons[ $name ] = array(
				'label' => str_replace( '-', ' ', $name ),
				'class' => 'dashicons dashicons-' . $name,
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
		return in_array( $name, $this->names(), true );
	}

	/**
	 * A span with the icon classes. Loads the Dashicons font on the front end.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public function render( string $name ): string {
		wp_enqueue_style( 'dashicons' );

		return sprintf( '<span class="cfmb-icon dashicons dashicons-%s" aria-hidden="true"></span>', esc_attr( $name ) );
	}

	/**
	 * Names from core's stylesheet, cached per WordPress version.
	 *
	 * @return string[]
	 */
	private function names(): array {
		if ( null !== $this->names ) {
			return $this->names;
		}

		$cache_key = 'cfmb_dashicons_' . md5( get_bloginfo( 'version' ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			$this->names = $cached;

			return $this->names;
		}

		$file = ABSPATH . WPINC . '/css/dashicons.css';
		$css  = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local core file.

		preg_match_all( '/\.dashicons-([a-z0-9-]+):before/', $css, $matches );

		$this->names = array_values( array_unique( $matches[1] ) );
		set_transient( $cache_key, $this->names, MONTH_IN_SECONDS );

		return $this->names;
	}
}
