<?php
/**
 * Finds Field Block templates and their asset files.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * A template is a PHP file that gets included, so where it may come from is
 * fixed: the active theme and its parent, the plugins folders, and folders
 * added with the `cfmb/blocks/template_paths` filter. A path typed in the
 * builder is only used when it resolves to a .php file inside one of those.
 */
class Template_Locator {

	/**
	 * Folder name searched in the theme and parent theme.
	 */
	public const FOLDER = 'cfmb-blocks';

	/**
	 * Folders searched for templates by block name, child theme first.
	 *
	 * @return string[] Normalized absolute paths without a trailing slash.
	 */
	public function folders(): array {
		$folders = array_unique(
			array(
				get_stylesheet_directory() . '/' . self::FOLDER,
				get_template_directory() . '/' . self::FOLDER,
			)
		);

		/**
		 * Filters the folders searched for Field Block templates, in order.
		 *
		 * A template for cfmb/hero is found as hero.php or hero/render.php in
		 * the first folder that has one. Folders added here are also allowed
		 * as locations for template paths set in the builder.
		 *
		 * @param string[] $folders Absolute folder paths.
		 */
		$folders = (array) apply_filters( 'cfmb/blocks/template_paths', array_values( $folders ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return array_values( array_filter( array_map( array( $this, 'clean_dir' ), $folders ) ) );
	}

	/**
	 * Find the template for a block.
	 *
	 * @param string $slug       Block name without the namespace, e.g. hero.
	 * @param string $configured Path set in the builder or block.json, or empty to search by name.
	 * @param string $base       Folder a relative configured path is read from first, e.g. the block.json folder.
	 * @return array{path: string, error: string} Path, or an error message for editors.
	 */
	public function locate( string $slug, string $configured = '', string $base = '' ): array {
		$candidates = '' !== $configured ? $this->configured_candidates( $configured, $base ) : $this->named_candidates( $slug );

		foreach ( $candidates as $candidate ) {
			if ( ! is_file( $candidate ) ) {
				continue;
			}

			if ( ! $this->is_allowed( $candidate ) ) {
				return array(
					'path'  => '',
					/* translators: %s: file path */
					'error' => sprintf( __( 'The template %s is outside the folders CFMB may load templates from (your theme, the plugins folder, or a folder added with the cfmb/blocks/template_paths filter).', 'cfmb' ), $this->display_path( $candidate ) ),
				);
			}

			return array(
				'path'  => (string) realpath( $candidate ),
				'error' => '',
			);
		}

		if ( '' !== $configured ) {
			return array(
				'path'  => '',
				/* translators: %s: file path */
				'error' => sprintf( __( 'The template file %s was not found.', 'cfmb' ), $configured ),
			);
		}

		return array(
			'path'  => '',
			/* translators: 1: file name, 2: folder name */
			'error' => sprintf( __( 'Add a template for this block: %1$s in the %2$s folder of your theme.', 'cfmb' ), $slug . '.php', self::FOLDER ),
		);
	}

	/**
	 * Whether a file may be included as a template: an existing .php file
	 * inside the theme, the parent theme, a plugins folder or a template folder.
	 *
	 * @param string $file Path.
	 * @return bool
	 */
	public function is_allowed( string $file ): bool {
		if ( false !== strpos( $file, "\0" ) || false !== strpos( $file, '://' ) ) {
			return false;
		}

		$real = realpath( $file );

		if ( false === $real || ! is_file( $real ) || 'php' !== strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) ) {
			return false;
		}

		$real = $this->comparable( $real );

		foreach ( $this->roots() as $root ) {
			if ( 0 === strpos( $real, $this->comparable( $root ) . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A file next to the template with another extension: hero.php gives hero.css.
	 *
	 * @param string $template  Template path.
	 * @param string $extension Extension without the dot.
	 * @return string Path, or empty when there is no such file.
	 */
	public function sibling( string $template, string $extension ): string {
		$file = dirname( $template ) . '/' . pathinfo( $template, PATHINFO_FILENAME ) . '.' . $extension;

		return is_file( $file ) ? wp_normalize_path( $file ) : '';
	}

	/**
	 * Public URL of a file inside wp-content or the WordPress folder.
	 *
	 * @param string $file Absolute path.
	 * @return string Empty when the file is not reachable over the web.
	 */
	public function url( string $file ): string {
		$file = wp_normalize_path( $file );
		$maps = array(
			wp_normalize_path( get_stylesheet_directory() ) => get_stylesheet_directory_uri(),
			wp_normalize_path( get_template_directory() )   => get_template_directory_uri(),
			wp_normalize_path( WP_PLUGIN_DIR )              => plugins_url(),
			wp_normalize_path( WP_CONTENT_DIR )             => content_url(),
			wp_normalize_path( ABSPATH )                    => site_url(),
		);

		foreach ( $maps as $dir => $url ) {
			$dir = untrailingslashit( $dir );

			if ( 0 === strpos( $this->comparable( $file ), $this->comparable( $dir ) . '/' ) ) {
				return untrailingslashit( $url ) . substr( $file, strlen( $dir ) );
			}
		}

		return '';
	}

	/**
	 * Folders templates may live in.
	 *
	 * @return string[]
	 */
	private function roots(): array {
		$roots = array_merge(
			array( get_stylesheet_directory(), get_template_directory(), WP_PLUGIN_DIR ),
			defined( 'WPMU_PLUGIN_DIR' ) ? array( WPMU_PLUGIN_DIR ) : array(),
			$this->folders()
		);

		$clean = array();
		foreach ( $roots as $root ) {
			$real = realpath( (string) $root );
			if ( false !== $real ) {
				$clean[] = untrailingslashit( wp_normalize_path( $real ) );
			}
		}

		return array_unique( $clean );
	}

	/**
	 * Places a builder path may point at, in order. Relative paths are read
	 * from the block.json folder, the child theme and the parent theme.
	 *
	 * @param string $configured Configured path.
	 * @param string $base       Folder to try first.
	 * @return string[]
	 */
	private function configured_candidates( string $configured, string $base ): array {
		$path = wp_normalize_path( $configured );

		if ( path_is_absolute( $path ) ) {
			return array( $path );
		}

		$path  = ltrim( $path, '/' );
		$bases = array_filter( array( $base, get_stylesheet_directory(), get_template_directory() ) );

		return array_values( array_unique( array_map( static fn( $dir ) => untrailingslashit( wp_normalize_path( $dir ) ) . '/' . $path, $bases ) ) );
	}

	/**
	 * Places a template for a block name may be.
	 *
	 * @param string $slug Block name without namespace.
	 * @return string[]
	 */
	private function named_candidates( string $slug ): array {
		$candidates = array();

		foreach ( $this->folders() as $folder ) {
			$candidates[] = $folder . '/' . $slug . '.php';
			$candidates[] = $folder . '/' . $slug . '/render.php';
		}

		return $candidates;
	}

	/**
	 * Normalized folder path, or empty for anything that is not a plain path.
	 *
	 * @param mixed $dir Folder.
	 * @return string
	 */
	private function clean_dir( $dir ): string {
		if ( ! is_string( $dir ) || '' === $dir || false !== strpos( $dir, '://' ) || false !== strpos( $dir, "\0" ) ) {
			return '';
		}

		return untrailingslashit( wp_normalize_path( $dir ) );
	}

	/**
	 * Path form used for prefix checks. Windows paths compare without case.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function comparable( string $path ): string {
		$path = untrailingslashit( wp_normalize_path( $path ) );

		return '\\' === DIRECTORY_SEPARATOR ? strtolower( $path ) : $path;
	}

	/**
	 * Path relative to wp-content for messages, so full server paths are not shown.
	 *
	 * @param string $file Path.
	 * @return string
	 */
	private function display_path( string $file ): string {
		$file    = wp_normalize_path( $file );
		$content = untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		return 0 === strpos( $file, $content . '/' ) ? 'wp-content' . substr( $file, strlen( $content ) ) : basename( $file );
	}
}
