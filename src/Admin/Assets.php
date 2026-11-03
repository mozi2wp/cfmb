<?php
/**
 * Admin script and style registration.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the compiled bundles and enqueues them only on screens that use them.
 */
class Assets {

	/**
	 * Bundles in build/.
	 */
	private const BUNDLES = array( 'builder', 'fields', 'map', 'blocks', 'bindings', 'connections', 'seo', 'location-watch' );

	/**
	 * Handles a bundle needs besides the packages it imports.
	 */
	private const EXTRA_DEPENDENCIES = array(
		// The Field Block editor renders forms with the fields bundle's components.
		'blocks'         => array( 'cfmb-fields' ),
		// Both read the forms the fields bundle mounts.
		'seo'            => array( 'cfmb-fields' ),
		'location-watch' => array( 'cfmb-fields' ),
	);

	/**
	 * Whether handles are registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_bundles' ), 5 );
	}

	/**
	 * Register script and style handles for every bundle.
	 *
	 * @return void
	 */
	public function register_bundles(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		foreach ( self::BUNDLES as $bundle ) {
			$asset_file = CFMB_DIR . "build/{$bundle}.asset.php";

			if ( ! file_exists( $asset_file ) ) {
				continue;
			}

			$asset  = require $asset_file;
			$handle = "cfmb-{$bundle}";

			$dependencies = array_merge( $asset['dependencies'], self::EXTRA_DEPENDENCIES[ $bundle ] ?? array() );

			wp_register_script( $handle, CFMB_URL . "build/{$bundle}.js", $dependencies, $asset['version'], true );
			wp_set_script_translations( $handle, 'cfmb' );

			if ( file_exists( CFMB_DIR . "build/{$bundle}.css" ) ) {
				wp_register_style( $handle, CFMB_URL . "build/{$bundle}.css", array( 'wp-components' ), $asset['version'] );

				// wp-scripts writes an -rtl file; the styles use logical properties, so it is optional.
				if ( file_exists( CFMB_DIR . "build/{$bundle}-rtl.css" ) ) {
					wp_style_add_data( $handle, 'rtl', 'replace' );
				}
			}
		}
	}

	/**
	 * Enqueue a bundle, optionally with a config object for the script.
	 *
	 * @param string                    $bundle     builder, fields, map, blocks, bindings, connections, seo or location-watch.
	 * @param string                    $config_var Global variable name for the config, or empty.
	 * @param array<string, mixed>|null $config     Config passed to the script.
	 * @return void
	 */
	public function enqueue( string $bundle, string $config_var = '', ?array $config = null ): void {
		$this->register_bundles();

		$handle = "cfmb-{$bundle}";

		wp_enqueue_script( $handle );
		wp_enqueue_style( $handle );

		if ( '' !== $config_var && null !== $config ) {
			wp_add_inline_script( $handle, sprintf( 'window.%s = %s;', $config_var, wp_json_encode( $config ) ), 'before' );
		}
	}
}
