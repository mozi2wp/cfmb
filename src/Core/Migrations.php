<?php
/**
 * Versioned database changes.
 *
 * @package CFMB
 */

namespace CFMB\Core;

use CFMB\Connections\Connection_Store;
use CFMB\Switching\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Brings the database up to Activator::DB_VERSION one step at a time.
 * Runs on activation and on `init` after an update; each step is safe to
 * repeat (dbDelta only adds what is missing).
 *
 * 1. Options (cfmb_settings, cfmb_db_version).
 * 2. Connection links table ({prefix}cfmb_connections).
 * 3. Switch Assistant backup table ({prefix}cfmb_switch_backup).
 */
final class Migrations {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'maybe_run' ), 1 );
	}

	/**
	 * Run missing steps when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		if ( (int) get_option( 'cfmb_db_version', 0 ) < Activator::DB_VERSION ) {
			self::run();
		}
	}

	/**
	 * Run every step after the stored version.
	 *
	 * @return void
	 */
	public static function run(): void {
		$version = (int) get_option( 'cfmb_db_version', 0 );

		if ( $version < 2 ) {
			Connection_Store::install();
		}

		if ( $version < 3 ) {
			Backup::install();
		}

		update_option( 'cfmb_db_version', Activator::DB_VERSION, true );
	}
}
