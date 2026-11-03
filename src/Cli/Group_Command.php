<?php
/**
 * wp cfmb group
 *
 * @package CFMB
 */

namespace CFMB\Cli;

use CFMB\Core\Plugin;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * List, read, turn on and off, and delete Field Groups.
 */
class Group_Command {

	/**
	 * CFMB.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin CFMB.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * List Field Groups.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml, ids or count.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb group list
	 *
	 * @subcommand list
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function list_( $args, $assoc_args ): void {
		$rows = array_map(
			static fn( Group $group ) => array(
				'key'    => $group->key(),
				'title'  => $group->title(),
				'active' => $group->active() ? 'yes' : 'no',
				'fields' => count( $group->stored_fields() ),
				'source' => $group->source(),
			),
			$this->groups()->all()
		);

		$format = (string) ( $assoc_args['format'] ?? 'table' );

		if ( 'ids' === $format ) {
			\WP_CLI::line( implode( ' ', array_column( $rows, 'key' ) ) );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'key', 'title', 'active', 'fields', 'source' ) );
	}

	/**
	 * Print a Field Group as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Group key.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb group get grp_1a2b3c4d5e
	 *
	 * @param string[] $args Group key.
	 * @return void
	 */
	public function get( $args ): void {
		\WP_CLI::line( (string) wp_json_encode( $this->find( $args[0] )->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Show a Field Group on edit screens.
	 *
	 * ## OPTIONS
	 *
	 * <key>...
	 * : Group keys.
	 *
	 * @param string[] $args Group keys.
	 * @return void
	 */
	public function activate( $args ): void {
		$this->set_active( $args, true );
	}

	/**
	 * Hide a Field Group from edit screens. Its values are kept.
	 *
	 * ## OPTIONS
	 *
	 * <key>...
	 * : Group keys.
	 *
	 * @param string[] $args Group keys.
	 * @return void
	 */
	public function deactivate( $args ): void {
		$this->set_active( $args, false );
	}

	/**
	 * Move a Field Group to the trash, or delete it for good. Saved values are kept.
	 *
	 * ## OPTIONS
	 *
	 * <key>...
	 * : Group keys.
	 *
	 * [--force]
	 * : Delete for good instead of trashing.
	 *
	 * @param string[]             $args       Group keys.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function delete( $args, $assoc_args ): void {
		foreach ( $args as $key ) {
			$done = ! empty( $assoc_args['force'] ) ? $this->groups()->delete( $key ) : $this->groups()->trash( $key );

			if ( ! $done ) {
				\WP_CLI::error( "{$key}: not found in the database." );
			}

			\WP_CLI::log( $key . ( ! empty( $assoc_args['force'] ) ? ': deleted' : ': moved to the trash' ) );
		}

		\WP_CLI::success( count( $args ) . ' done.' );
	}

	/**
	 * Turn groups on or off.
	 *
	 * @param string[] $keys   Group keys.
	 * @param bool     $active On or off.
	 * @return void
	 */
	private function set_active( array $keys, bool $active ): void {
		foreach ( $keys as $key ) {
			$group = $this->find( $key );

			if ( 'db' !== $group->source() ) {
				\WP_CLI::error( "{$key} is defined in {$group->source()}; change it there." );
			}

			$saved = $this->groups()->save( array_merge( $group->to_array(), array( 'active' => $active ) ) );

			if ( is_wp_error( $saved ) ) {
				\WP_CLI::error( "{$key}: " . $saved->get_error_message() );
			}
		}

		\WP_CLI::success( count( $keys ) . ( $active ? ' activated.' : ' deactivated.' ) );
	}

	/**
	 * Find a group or stop.
	 *
	 * @param string $key Group key.
	 * @return Group
	 */
	private function find( string $key ): Group {
		$group = $this->groups()->find( $key );

		if ( ! $group ) {
			\WP_CLI::error( "No field group with the key {$key}." );
		}

		return $group;
	}

	/**
	 * Group repository.
	 *
	 * @return Group_Repository
	 */
	private function groups(): Group_Repository {
		return $this->plugin->get( Group_Repository::class );
	}
}
