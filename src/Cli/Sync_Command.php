<?php
/**
 * wp cfmb sync
 *
 * @package CFMB
 */

namespace CFMB\Cli;

use CFMB\Core\Plugin;
use CFMB\Sync\Sync_Files;
use CFMB\Sync\Sync_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Compare and move definitions between the database and Schema Sync files.
 */
class Sync_Command {

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
	 * Show the sync state of every definition.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * [--changed]
	 * : Only list definitions that are not in sync.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb sync status
	 *     wp cfmb sync status --changed --format=json
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function status( $args, $assoc_args ): void {
		$rows = $this->sync()->rows();

		if ( ! empty( $assoc_args['changed'] ) ) {
			$rows = array_filter( $rows, static fn( array $row ) => ! in_array( $row['status'], array( Sync_Manager::SYNCED, Sync_Manager::CODE ), true ) );
		}

		$table = array_map(
			static fn( array $row ) => array(
				'kind'   => $row['kind'],
				'key'    => $row['key'],
				'title'  => $row['title'],
				'source' => $row['source'],
				'status' => $row['status'],
				'file'   => $row['file'] ? $row['file']['location'] : '',
			),
			array_values( $rows )
		);

		\WP_CLI\Utils\format_items( (string) ( $assoc_args['format'] ?? 'table' ), $table, array( 'kind', 'key', 'title', 'source', 'status', 'file' ) );
	}

	/**
	 * Write database definitions to their files.
	 *
	 * ## OPTIONS
	 *
	 * [<key>...]
	 * : Keys to write, such as grp_1a2b3c4d5e.
	 *
	 * [--all]
	 * : Write every definition whose database copy changed or has no file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb sync push --all
	 *
	 * @param string[]             $args       Keys.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function push( $args, $assoc_args ): void {
		$this->run( 'push', $args, ! empty( $assoc_args['all'] ), array( Sync_Manager::DB_CHANGED, Sync_Manager::NO_FILE ) );
	}

	/**
	 * Save file definitions to the database.
	 *
	 * ## OPTIONS
	 *
	 * [<key>...]
	 * : Keys to import, such as grp_1a2b3c4d5e.
	 *
	 * [--all]
	 * : Import every definition whose file changed or exists only as a file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb sync pull --all
	 *
	 * @param string[]             $args       Keys.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function pull( $args, $assoc_args ): void {
		$this->run( 'pull', $args, ! empty( $assoc_args['all'] ), array( Sync_Manager::FILE_CHANGED, Sync_Manager::FILE_ONLY ) );
	}

	/**
	 * Push or pull keys, or every row in the given states.
	 *
	 * @param string   $action   push or pull.
	 * @param string[] $keys     Keys.
	 * @param bool     $all      Whether to act on every row in $statuses.
	 * @param string[] $statuses States --all acts on.
	 * @return void
	 */
	private function run( string $action, array $keys, bool $all, array $statuses ): void {
		$sync  = $this->sync();
		$pairs = array();

		if ( $all ) {
			foreach ( $sync->rows() as $row ) {
				if ( in_array( $row['status'], $statuses, true ) ) {
					$pairs[] = array( $row['kind'], $row['key'] );
				}
			}
		} else {
			foreach ( $keys as $key ) {
				$pairs[] = array( Sync_Files::kind_of( $key ), $key );
			}
		}

		if ( ! $pairs ) {
			\WP_CLI::success( $all ? 'Nothing to do.' : 'Pass one or more keys, or --all.' );
			return;
		}

		$failed = 0;

		foreach ( $pairs as list( $kind, $key ) ) {
			if ( '' === $kind ) {
				\WP_CLI::warning( "{$key}: not a CFMB key." );
				++$failed;
				continue;
			}

			$result = $sync->$action( $kind, $key );

			if ( is_wp_error( $result ) ) {
				\WP_CLI::warning( "{$key}: " . $result->get_error_message() );
				++$failed;
				continue;
			}

			\WP_CLI::log( "{$key}: {$result['status']}" );
		}

		if ( $failed ) {
			\WP_CLI::error( "{$failed} of " . count( $pairs ) . ' failed.' );
		}

		\WP_CLI::success( count( $pairs ) . ' done.' );
	}

	/**
	 * Sync service.
	 *
	 * @return Sync_Manager
	 */
	private function sync(): Sync_Manager {
		return $this->plugin->get( Sync_Manager::class );
	}
}
