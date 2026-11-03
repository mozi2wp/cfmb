<?php
/**
 * wp cfmb export / import
 *
 * @package CFMB
 */

namespace CFMB\Cli;

use CFMB\Core\Plugin;
use CFMB\Sync\Php_Exporter;
use CFMB\Sync\Sync_Files;
use CFMB\Sync\Sync_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Export definitions as JSON or PHP, and import JSON files.
 */
class Transfer_Command {

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
	 * Export field groups, post types, taxonomies and options pages.
	 *
	 * ## OPTIONS
	 *
	 * [<key>...]
	 * : Keys to export. Default: everything.
	 *
	 * [--format=<format>]
	 * : json writes one file per definition; php prints cfmb_register_*() calls.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - php
	 * ---
	 *
	 * [--dir=<dir>]
	 * : Folder for JSON files. Default: the current folder.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb export --dir=wp-content/themes/my-theme/cfmb-json
	 *     wp cfmb export grp_1a2b3c4d5e --format=php > fields.php
	 *
	 * @param string[]             $args       Keys.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function export( $args, $assoc_args ): void {
		$pairs = array();
		foreach ( $args as $key ) {
			$pairs[] = array(
				'kind' => Sync_Files::kind_of( $key ),
				'key'  => $key,
			);
		}

		$definitions = $this->sync()->export( $pairs );

		if ( ! $definitions ) {
			\WP_CLI::error( 'Nothing to export.' );
		}

		if ( 'php' === ( $assoc_args['format'] ?? 'json' ) ) {
			\WP_CLI::line( "<?php\n" . Php_Exporter::export( $definitions ) );
			return;
		}

		$dir = untrailingslashit( (string) ( $assoc_args['dir'] ?? getcwd() ) );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			\WP_CLI::error( "Could not create {$dir}." );
		}

		foreach ( $definitions as $definition ) {
			$data = array_merge(
				array(
					'key'  => $definition['item']['key'],
					'kind' => $definition['kind'],
				),
				$definition['item'],
				array( 'modified' => time() )
			);
			$path = $dir . '/' . $definition['item']['key'] . '.json';

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI writes to a folder the user named.
			if ( false === file_put_contents( $path, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ) ) {
				\WP_CLI::error( "Could not write {$path}." );
			}

			\WP_CLI::log( $path );
		}

		\WP_CLI::success( count( $definitions ) . ' exported.' );
	}

	/**
	 * Import definitions from JSON files into the database.
	 *
	 * ## OPTIONS
	 *
	 * <file>...
	 * : JSON files, each holding one definition or a list of them.
	 *
	 * [--dry-run]
	 * : Check the files without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb import exports/*.json
	 *
	 * @param string[]             $args       Files.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function import( $args, $assoc_args ): void {
		$sync  = $this->sync();
		$count = 0;

		foreach ( $args as $path ) {
			if ( ! is_readable( $path ) ) {
				\WP_CLI::error( "Can't read {$path}." );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file named on the command line.
			$data  = json_decode( (string) file_get_contents( $path ), true );
			$items = is_array( $data ) && isset( $data['key'] ) ? array( $data ) : ( is_array( $data ) ? $data : array() );

			foreach ( $items as $item ) {
				$key  = is_array( $item ) ? (string) ( $item['key'] ?? '' ) : '';
				$kind = Sync_Files::kind_of( $key );

				if ( '' === $kind ) {
					\WP_CLI::warning( "{$path}: skipped an entry without a CFMB key." );
					continue;
				}

				unset( $item['modified'], $item['kind'] );

				if ( ! empty( $assoc_args['dry-run'] ) ) {
					\WP_CLI::log( "{$key}: would import \"" . ( $item['title'] ?? '' ) . '"' );
					++$count;
					continue;
				}

				$saved = $sync->save( $kind, $item, true );

				if ( is_wp_error( $saved ) ) {
					\WP_CLI::warning( "{$key}: " . $saved->get_error_message() );
					continue;
				}

				\WP_CLI::log( "{$key}: imported" );
				++$count;
			}
		}

		\WP_CLI::success( "{$count} imported." );
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
