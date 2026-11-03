<?php
/**
 * WP-CLI commands.
 *
 * @package CFMB
 */

namespace CFMB\Cli;

use CFMB\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `wp cfmb …` commands when WP-CLI runs.
 */
final class Cli {

	/**
	 * Register the commands.
	 *
	 * @param Plugin $plugin CFMB.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'cfmb sync', new Sync_Command( $plugin ) );
		\WP_CLI::add_command( 'cfmb group', new Group_Command( $plugin ) );
		\WP_CLI::add_command( 'cfmb field', new Field_Command() );
		\WP_CLI::add_command( 'cfmb export', array( new Transfer_Command( $plugin ), 'export' ) );
		\WP_CLI::add_command( 'cfmb import', array( new Transfer_Command( $plugin ), 'import' ) );
		\WP_CLI::add_command( 'cfmb doctor', new Doctor_Command( $plugin ) );
		\WP_CLI::add_command( 'cfmb switch', new Switch_Command( $plugin ) );
	}
}
