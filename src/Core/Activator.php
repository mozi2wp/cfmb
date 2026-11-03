<?php
/**
 * Activation tasks.
 *
 * @package CFMB
 */

namespace CFMB\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Sets up options and tables on activation.
 */
final class Activator {

	public const DB_VERSION = 3;

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// add_option() never overwrites, so reactivating keeps the user's settings.
		add_option(
			'cfmb_settings',
			array(
				'delete_data_on_uninstall' => false,
			)
		);
		add_option( 'cfmb_db_version', 1 );
		Migrations::run();

		// Post types and taxonomies made in CFMB need their URLs once CFMB is back.
		update_option( 'cfmb_flush_rewrite_rules', 1, true );
	}
}
