<?php
/**
 * Definitions from code and Schema Sync files.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

use CFMB\Sync\Sync_Files;

defined( 'ABSPATH' ) || exit;

/**
 * Field Groups, post types, taxonomies and options pages that are not
 * stored in the database: registered with cfmb_register_*() or found in
 * Schema Sync files. Code wins over a file with the same key; the
 * repositories let the database win over both.
 */
class Local_Definitions {

	/**
	 * Definitions registered in code, by kind and key.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private array $code = array();

	/**
	 * Schema Sync files.
	 *
	 * @var Sync_Files
	 */
	private Sync_Files $files;

	/**
	 * Changes each time a definition is added or files change, so repositories reload.
	 *
	 * @var int
	 */
	private int $revision = 0;

	/**
	 * Constructor.
	 *
	 * @param Sync_Files $files Schema Sync files.
	 */
	public function __construct( Sync_Files $files ) {
		$this->files = $files;
	}

	/**
	 * Register a definition in code.
	 *
	 * @param string               $kind group, post_type, taxonomy or options_page.
	 * @param array<string, mixed> $item Definition with a key such as grp_… or cpt_….
	 * @return bool False when the key is missing or has the wrong prefix.
	 */
	public function add_code( string $kind, array $item ): bool {
		$key = is_string( $item['key'] ?? null ) ? $item['key'] : '';

		if ( Sync_Files::kind_of( $key ) !== $kind || ! Key_Generator::is_valid( $key, substr( $key, 0, 4 ) ) ) {
			return false;
		}

		$this->code[ $kind ][ $key ] = array_merge(
			$item,
			array(
				'source' => 'code',
				'file'   => '',
			)
		);
		++$this->revision;

		return true;
	}

	/**
	 * Definitions of one kind from code and files, by key.
	 *
	 * @param string $kind Kind.
	 * @return array<string, array<string, mixed>>
	 */
	public function items( string $kind ): array {
		$items = $this->code[ $kind ] ?? array();

		foreach ( $this->files->all() as $key => $file ) {
			if ( $file['kind'] === $kind && ! isset( $items[ $key ] ) ) {
				$items[ $key ] = array_merge(
					$file['item'],
					array(
						'source' => 'file',
						'file'   => $file['path'],
					)
				);
			}
		}

		return $items;
	}

	/**
	 * Definitions of one kind registered in code, by key.
	 *
	 * @param string $kind Kind.
	 * @return array<string, array<string, mixed>>
	 */
	public function code( string $kind ): array {
		return $this->code[ $kind ] ?? array();
	}

	/**
	 * Note that files changed on disk.
	 *
	 * @return void
	 */
	public function files_changed(): void {
		$this->files->reset();
		++$this->revision;
	}

	/**
	 * Current revision.
	 *
	 * @return int
	 */
	public function revision(): int {
		return $this->revision;
	}
}
