<?php
/**
 * Compatibility Mode: ACF function names for themes built for ACF.
 *
 * @package CFMB
 */

namespace CFMB\Compat;

use CFMB\Admin\Settings_Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Off by default; turned on under CFMB > Settings. While it is on, the
 * functions in functions.php (get_field(), have_rows() and the rest) exist
 * and call their cfmb_ versions, so templates written for ACF keep working.
 *
 * It never runs next to ACF, ACF Pro or Secure Custom Fields: the setting
 * can't be turned on while one is active, and if one is activated later,
 * Compatibility Mode turns itself off and says so on CFMB screens. Each
 * function is also guarded with function_exists().
 *
 * Nothing in this folder loads unless the setting is on.
 */
class Compat_Mode {

	public const SETTING = 'compat_mode';

	public const NOTICE_OPTION = 'cfmb_compat_switched_off';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// After every plugin file has loaded, so ACF or SCF is detectable, and before themes.
		add_action( 'plugins_loaded', array( $this, 'maybe_load' ), 1 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Load the functions, or switch off when ACF or SCF is active.
	 *
	 * @return void
	 */
	public function maybe_load(): void {
		if ( empty( Settings_Screen::current()[ self::SETTING ] ) ) {
			return;
		}

		$blocker = self::blocker();

		if ( '' !== $blocker ) {
			$settings                  = Settings_Screen::current();
			$settings[ self::SETTING ] = false;

			update_option( Settings_Screen::OPTION, $settings );
			update_option( self::NOTICE_OPTION, $blocker, false );

			return;
		}

		require_once __DIR__ . '/functions.php';
	}

	/**
	 * Name of the active plugin that blocks Compatibility Mode, or empty.
	 *
	 * @return string
	 */
	public static function blocker(): string {
		if ( defined( 'SCF_VERSION' ) || class_exists( 'SCF' ) ) {
			return 'Secure Custom Fields';
		}

		if ( defined( 'ACF_PRO' ) && ACF_PRO ) {
			return 'ACF Pro';
		}

		if ( class_exists( 'ACF' ) || defined( 'ACF_VERSION' ) ) {
			return 'Advanced Custom Fields';
		}

		return '';
	}

	/**
	 * Tell admins on CFMB screens that Compatibility Mode switched itself off.
	 *
	 * @return void
	 */
	public function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$name   = (string) get_option( self::NOTICE_OPTION, '' );

		if ( '' === $name || ! $screen || false === strpos( (string) $screen->id, 'cfmb' ) || ! current_user_can( cfmb_admin_capability() ) ) {
			return;
		}

		delete_option( self::NOTICE_OPTION );

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: plugin name, e.g. Advanced Custom Fields */
					__( 'Compatibility Mode was turned off because %s is active. Both would define get_field() and the other ACF functions. Deactivate it, then turn Compatibility Mode back on under CFMB > Settings.', 'cfmb' ),
					$name
				)
			)
		);
	}
}
