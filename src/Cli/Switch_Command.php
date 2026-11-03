<?php
/**
 * wp cfmb switch
 *
 * @package CFMB
 */

namespace CFMB\Cli;

use CFMB\Core\Plugin;
use CFMB\Switching\Switch_Assistant;

defined( 'ABSPATH' ) || exit;

/**
 * Move an ACF or Secure Custom Fields site to CFMB from the command line.
 * The same steps as CFMB > Switch Assistant.
 */
class Switch_Command {

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
	 * List what ACF or SCF has and what is imported already.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<source>]
	 * : Another plugin to switch from, such as metabox, jetengine, pods or
	 * toolset (with CFMB Pro). Leave out for ACF and SCF.
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb switch scan
	 *     wp cfmb switch scan --source=metabox
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function scan( $args, $assoc_args ): void {
		$source = (string) ( $assoc_args['source'] ?? '' );

		if ( '' !== $source ) {
			$this->scan_source( $source, (string) ( $assoc_args['format'] ?? 'table' ) );
			return;
		}

		$scan = $this->assistant()->scan();
		$rows = array();

		foreach ( array( 'groups' => 'group', 'postTypes' => 'post_type', 'taxonomies' => 'taxonomy', 'optionsPages' => 'options_page' ) as $list => $kind ) {
			foreach ( (array) $scan[ $list ] as $item ) {
				$rows[] = array(
					'kind'     => $kind,
					'key'      => $item['key'],
					'title'    => $item['title'],
					'imported' => $item['imported'] ? 'yes' : 'no',
					'notes'    => isset( $item['notes'] ) ? count( (array) $item['notes'] ) : 0,
				);
			}
		}

		\WP_CLI\Utils\format_items( (string) ( $assoc_args['format'] ?? 'table' ), $rows, array( 'kind', 'key', 'title', 'imported', 'notes' ) );

		if ( $scan['sourceActive'] ) {
			\WP_CLI::warning( __( 'ACF or SCF is active. Groups are imported inactive and values convert after it is deactivated.', 'cfmb' ) );
		}
	}

	/**
	 * Import everything, then convert values unless ACF or SCF is active.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<source>]
	 * : Another plugin to switch from (with CFMB Pro). Leave out for ACF and SCF.
	 *
	 * [--skip-values]
	 * : Import definitions only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb switch run
	 *     wp cfmb switch run --source=pods
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function run( $args, $assoc_args ): void {
		$assistant = $this->assistant();
		$source_id = (string) ( $assoc_args['source'] ?? '' );
		$source    = '' !== $source_id ? $assistant->source( $source_id ) : null;
		$keys      = static fn( array $items ): array => array_map( static fn( array $item ): string => (string) $item['key'], array_filter( $items, static fn( array $item ): bool => empty( $item['imported'] ) ) );

		if ( '' !== $source_id && ! $source ) {
			\WP_CLI::error( __( 'That plugin can\'t be switched from here.', 'cfmb' ) );
		}

		if ( $source ) {
			$scan   = $assistant->scan_source( $source_id );
			$picked = array();

			foreach ( is_wp_error( $scan ) ? array() : (array) $scan['sections'] as $section ) {
				$picked[ (string) $section['id'] ] = $keys( (array) $section['items'] );
			}

			$run = $assistant->import_source( $source_id, $picked );
		} else {
			$scan = $assistant->scan();
			$run  = $assistant->import(
				array(
					'groups'        => $keys( (array) $scan['groups'] ),
					'post_types'    => $keys( (array) $scan['postTypes'] ),
					'taxonomies'    => $keys( (array) $scan['taxonomies'] ),
					'options_pages' => $keys( (array) $scan['optionsPages'] ),
				)
			);
		}

		if ( is_wp_error( $run ) ) {
			\WP_CLI::error( $run->get_error_message() );
		}

		/* translators: 1: number of definitions, 2: run ID */
		\WP_CLI::log( sprintf( __( 'Imported %1$d definitions (run %2$s).', 'cfmb' ), (int) $run['created'], (string) $run['id'] ) );

		foreach ( (array) $run['notes'] as $note ) {
			\WP_CLI::log( '- ' . $note );
		}

		if ( ! empty( $assoc_args['skip-values'] ) || ( $source ? $source->active() : Switch_Assistant::source_active() ) ) {
			\WP_CLI::success( __( 'Definitions imported. Convert values with: wp cfmb switch convert', 'cfmb' ) . ' ' . $run['id'] );
			return;
		}

		$this->convert( array( (string) $run['id'] ), array() );
	}

	/**
	 * Convert the values of a run.
	 *
	 * ## OPTIONS
	 *
	 * <run>
	 * : Run ID.
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function convert( $args, $assoc_args ): void {
		$id = (string) ( $args[0] ?? '' );

		do {
			$run = $this->assistant()->convert( $id );

			if ( is_wp_error( $run ) ) {
				\WP_CLI::error( $run->get_error_message() );
			}
		} while ( 'done' !== $run['status'] );

		/* translators: 1: rows converted, 2: options moved, 3: options kept */
		\WP_CLI::success( sprintf( __( 'Values converted: %1$d rows changed, %2$d options page values moved, %3$d kept where a CFMB value existed.', 'cfmb' ), $run['counts']['converted'], $run['counts']['moved'], $run['counts']['kept'] ) );
	}

	/**
	 * Undo a run: restore the original values and trash what it created.
	 *
	 * ## OPTIONS
	 *
	 * <run>
	 * : Run ID.
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function undo( $args, $assoc_args ): void {
		$id = (string) ( $args[0] ?? '' );

		\WP_CLI::confirm( __( 'Restore the values ACF had and move the imported definitions to the trash?', 'cfmb' ), $assoc_args );

		do {
			$run = $this->assistant()->undo( $id );

			if ( is_wp_error( $run ) ) {
				\WP_CLI::error( $run->get_error_message() );
			}
		} while ( 'undone' !== $run['status'] );

		\WP_CLI::success( __( 'Undone.', 'cfmb' ) );
	}

	/**
	 * List what another source has.
	 *
	 * @param string $id     Source ID.
	 * @param string $format Output format.
	 * @return void
	 */
	private function scan_source( string $id, string $format ): void {
		$scan = $this->assistant()->scan_source( $id );

		if ( is_wp_error( $scan ) ) {
			\WP_CLI::error( $scan->get_error_message() );
		}

		$rows = array();

		foreach ( (array) $scan['sections'] as $section ) {
			foreach ( (array) $section['items'] as $item ) {
				$rows[] = array(
					'kind'     => $section['id'],
					'key'      => $item['key'],
					'title'    => $item['title'],
					'imported' => ! empty( $item['imported'] ) ? 'yes' : 'no',
					'notes'    => isset( $item['notes'] ) ? count( (array) $item['notes'] ) : 0,
				);
			}
		}

		\WP_CLI\Utils\format_items( $format, $rows, array( 'kind', 'key', 'title', 'imported', 'notes' ) );

		if ( $scan['sourceActive'] ) {
			/* translators: %s: plugin name */
			\WP_CLI::warning( sprintf( __( '%s is active. Groups are imported inactive and values convert after it is deactivated.', 'cfmb' ), (string) $scan['label'] ) );
		}
	}

	/**
	 * Switch Assistant service.
	 *
	 * @return Switch_Assistant
	 */
	private function assistant(): Switch_Assistant {
		return $this->plugin->get( Switch_Assistant::class );
	}
}
