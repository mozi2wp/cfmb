<?php
/**
 * A plugin the Switch Assistant can move a site from, besides ACF and SCF.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Register one with the `cfmb/switch/sources` filter. CFMB keeps the runs,
 * the backup table and the screen; the source reads its plugin's data,
 * creates definitions (see Definition_Importer) and converts values a batch
 * at a time, copying every row it changes to Backup first.
 *
 * A run is a plain array stored by Switch_Assistant. CFMB owns these keys:
 * id, source, started, status, created, activate, counts, notes, done. The
 * source may add its own keys (cursor, jobs …); they must stay JSON-like.
 *
 * @since 0.15.0
 */
interface Switch_Source {

	/**
	 * Short ID stored with each run, such as "metabox".
	 *
	 * @return string Lowercase letters, numbers and dashes.
	 */
	public function id(): string;

	/**
	 * Plugin name shown on the screen.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether the plugin is active. While it is, groups import inactive and
	 * values are not converted, because both plugins would edit them.
	 *
	 * @return bool
	 */
	public function active(): bool;

	/**
	 * Whether the site has anything to import from this plugin.
	 *
	 * @return bool
	 */
	public function found(): bool;

	/**
	 * What can be imported, in sections.
	 *
	 * @return array{sections: array<int, array{id: string, title: string, items: array<int, array<string, mixed>>}>, notes?: string[]}
	 *         Each item: key, title, name (optional), fields (optional count),
	 *         imported (bool), notes (string[]).
	 */
	public function scan(): array;

	/**
	 * Create definitions for the picked items and fill in the run.
	 *
	 * @param array<string, string[]> $picked Item keys by section ID.
	 * @param array<string, mixed>    $run    New run; add to created, activate, notes.
	 * @return array<string, mixed>|WP_Error The run.
	 */
	public function import( array $picked, array $run );

	/**
	 * Convert the next batch of values.
	 *
	 * @param array<string, mixed> $run Run.
	 * @return array<string, mixed> The run; `done` true when everything is converted.
	 *                              counts: converted, moved, kept, items.
	 */
	public function convert( array $run ): array;

	/**
	 * Remove the next batch of what the run made outside definitions and
	 * backed-up rows, such as posts or links. Runs after Backup restored.
	 *
	 * @param array<string, mixed> $run Run.
	 * @return bool True when nothing is left to remove.
	 */
	public function undo( array $run ): bool;
}
