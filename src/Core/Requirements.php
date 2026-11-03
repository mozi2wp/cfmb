<?php
/**
 * Minimum PHP and WordPress version check.
 *
 * Loaded before the autoloader and kept to old syntax so it can still run,
 * and explain the problem, on PHP versions we do not support.
 *
 * @package CFMB
 */

namespace CFMB\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the environment before the plugin boots.
 */
class Requirements {

	/**
	 * Whether PHP and WordPress meet the minimum versions.
	 *
	 * @return bool
	 */
	public static function met() {
		return version_compare( PHP_VERSION, CFMB_MIN_PHP, '>=' )
			&& version_compare( get_bloginfo( 'version' ), CFMB_MIN_WP, '>=' );
	}

	/**
	 * Show an admin notice explaining why CFMB is not running.
	 *
	 * @return void
	 */
	public static function show_notice() {
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
	}

	/**
	 * Render the notice.
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version, 3: current PHP version, 4: current WordPress version */
			__( 'CFMB needs PHP %1$s and WordPress %2$s or newer. This site runs PHP %3$s and WordPress %4$s. CFMB is not active until both are updated.', 'custom-fields-meta-box' ),
			CFMB_MIN_PHP,
			CFMB_MIN_WP,
			PHP_VERSION,
			get_bloginfo( 'version' )
		);

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}
}
