<?php
/**
 * Schema Sync: keeps the database and JSON files in step.
 *
 * @package CFMB
 */

namespace CFMB\Sync;

use CFMB\Admin\Settings_Screen;
use CFMB\Content\Content_Repository;
use CFMB\Content\Options_Page_Repository;
use CFMB\Content\Post_Type_Repository;
use CFMB\Content\Taxonomy_Repository;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Schema\Local_Definitions;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Works out the sync state of every definition and moves changes between
 * the database and files.
 *
 * Each stored definition remembers the hash it had at its last sync
 * (`_cfmb_sync_hash`). Comparing that with the current database and file
 * copies tells which side changed: only the file (pull), only the database
 * (push), or both (conflict, solved with a merge). Saving in the builder
 * writes the file at once, unless the file changed outside CFMB.
 */
class Sync_Manager {

	/**
	 * Post meta holding the hash at the last sync.
	 */
	public const META = '_cfmb_sync_hash';

	public const SYNCED       = 'synced';
	public const FILE_CHANGED = 'file_changed';
	public const DB_CHANGED   = 'db_changed';
	public const CONFLICT     = 'conflict';
	public const FILE_ONLY    = 'file_only';
	public const NO_FILE      = 'no_file';
	public const CODE         = 'code';

	/**
	 * Files.
	 *
	 * @var Sync_Files
	 */
	private Sync_Files $files;

	/**
	 * Definitions from code and files.
	 *
	 * @var Local_Definitions
	 */
	private Local_Definitions $local;

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Field Group sanitizer.
	 *
	 * @var Group_Sanitizer
	 */
	private Group_Sanitizer $sanitizer;

	/**
	 * Content repositories by kind.
	 *
	 * @var array<string, Content_Repository>
	 */
	private array $content;

	/**
	 * Write the file on the next save even if it changed outside CFMB.
	 *
	 * @var bool
	 */
	private bool $force_write = false;

	/**
	 * Problems from automatic writes this request, by key.
	 *
	 * @var array<string, string>
	 */
	private array $write_notes = array();

	/**
	 * Constructor.
	 *
	 * @param Sync_Files              $files         Files.
	 * @param Local_Definitions       $local         Definitions from code and files.
	 * @param Group_Repository        $groups        Field Groups.
	 * @param Group_Sanitizer         $sanitizer     Field Group sanitizer.
	 * @param Post_Type_Repository    $post_types    Post types.
	 * @param Taxonomy_Repository     $taxonomies    Taxonomies.
	 * @param Options_Page_Repository $options_pages Options pages.
	 * @param Content_Repository|null $connections   Connections.
	 */
	public function __construct( Sync_Files $files, Local_Definitions $local, Group_Repository $groups, Group_Sanitizer $sanitizer, Post_Type_Repository $post_types, Taxonomy_Repository $taxonomies, Options_Page_Repository $options_pages, ?Content_Repository $connections = null ) {
		$this->files     = $files;
		$this->local     = $local;
		$this->groups    = $groups;
		$this->sanitizer = $sanitizer;
		$this->content   = array(
			'post_type'    => $post_types,
			'taxonomy'     => $taxonomies,
			'options_page' => $options_pages,
		);

		if ( $connections ) {
			$this->content['connection'] = $connections;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'cfmb/schema/saved', array( $this, 'on_saved' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		add_action( 'cfmb/schema/deleted', array( $this, 'on_deleted' ), 10, 2 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Kinds in display order.
	 *
	 * @return string[]
	 */
	public static function kinds(): array {
		return array( 'group', 'post_type', 'taxonomy', 'options_page', 'connection' );
	}

	/**
	 * Whether saving in the builder writes files.
	 *
	 * @return bool
	 */
	public function auto_write(): bool {
		return ! empty( Settings_Screen::current()['sync_auto_write'] );
	}

	/**
	 * The files service.
	 *
	 * @return Sync_Files
	 */
	public function files(): Sync_Files {
		return $this->files;
	}

	/**
	 * Write the file after a database save, unless it changed outside CFMB.
	 *
	 * @param string               $kind    Kind.
	 * @param array<string, mixed> $item    Saved definition.
	 * @param int                  $post_id Storage post ID.
	 * @return void
	 */
	public function on_saved( $kind, $item, $post_id ): void {
		$kind  = (string) $kind;
		$force = $this->force_write;

		$this->force_write = false;

		if ( ! in_array( $kind, self::kinds(), true ) || ( ! $force && ! $this->auto_write() ) ) {
			return;
		}

		$portable = $this->portable( $kind, (array) $item );
		$hash     = Portable::hash( $portable );
		$file     = $this->files->find( (string) $portable['key'] );

		if ( $file && ! $force ) {
			$file_hash = Portable::hash( $this->portable( $kind, $file['item'] ) );
			$base      = (string) get_post_meta( (int) $post_id, self::META, true );

			if ( $file_hash === $hash ) {
				update_post_meta( (int) $post_id, self::META, $hash );
				return;
			}

			// The file changed outside CFMB (a deploy, git pull or teammate); leave it for review.
			if ( $file_hash !== $base ) {
				$this->write_notes[ (string) $portable['key'] ] = self::CONFLICT;
				return;
			}
		}

		$written = $this->files->write( $kind, $portable );

		if ( is_wp_error( $written ) ) {
			$this->write_notes[ (string) $portable['key'] ] = $written->get_error_message();
			return;
		}

		update_post_meta( (int) $post_id, self::META, $hash );
		$this->local->files_changed();
	}

	/**
	 * Remove the file from the save folder when a definition is deleted for good.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return void
	 */
	public function on_deleted( $kind, $key ): void {
		if ( in_array( (string) $kind, self::kinds(), true ) ) {
			$this->files->delete( (string) $key );
			$this->local->files_changed();
		}
	}

	/**
	 * What happened to the file on the last automatic write, by key.
	 *
	 * @return array<string, string>
	 */
	public function write_notes(): array {
		return $this->write_notes;
	}

	/**
	 * Sync state of every definition.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function rows(): array {
		$rows = array();

		foreach ( self::kinds() as $kind ) {
			foreach ( $this->definitions( $kind ) as $item ) {
				$rows[] = $this->row( $kind, $item );
			}
		}

		return $rows;
	}

	/**
	 * Sync state of one definition.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return array<string, mixed>|null
	 */
	public function row_for( string $kind, string $key ): ?array {
		$item = $this->definition( $kind, $key );

		return $item ? $this->row( $kind, $item ) : null;
	}

	/**
	 * Differences between the database and file copies.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function diff( string $kind, string $key ) {
		$copies = $this->copies( $kind, $key );

		if ( is_wp_error( $copies ) ) {
			return $copies;
		}

		return array(
			'kind'    => $kind,
			'key'     => $key,
			'title'   => (string) $copies['db']['title'],
			'entries' => Diff::entries( $kind, $copies['db'], $copies['file'] ),
		);
	}

	/**
	 * Write the database copy to the file, replacing what is there.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return array<string, mixed>|WP_Error Updated row.
	 */
	public function push( string $kind, string $key ) {
		$item = $this->definition( $kind, $key );

		if ( ! $item || 'db' !== $item['source'] ) {
			return new WP_Error( 'cfmb_sync_missing', __( 'Only definitions saved in the database can be written to a file.', 'cfmb' ), array( 'status' => 400 ) );
		}

		$portable = $this->portable( $kind, $item );
		$written  = $this->files->write( $kind, $portable );

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		update_post_meta( (int) $item['id'], self::META, Portable::hash( $portable ) );
		$this->local->files_changed();

		return (array) $this->row_for( $kind, $key );
	}

	/**
	 * Save the file copy (or the code definition) to the database.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return array<string, mixed>|WP_Error Updated row.
	 */
	public function pull( string $kind, string $key ) {
		$file = $this->files->find( $key );
		$item = $this->definition( $kind, $key );

		if ( $item && 'code' === $item['source'] ) {
			$source = $item;
		} elseif ( $file && $file['kind'] === $kind ) {
			$source = $file['item'];
		} else {
			return new WP_Error( 'cfmb_sync_no_file', __( 'There is no file for this definition.', 'cfmb' ), array( 'status' => 404 ) );
		}

		$saved = $this->save( $kind, $this->portable( $kind, $source ), true );

		return is_wp_error( $saved ) ? $saved : (array) $this->row_for( $kind, $key );
	}

	/**
	 * Merge the database and file copies from a choice per difference,
	 * then save the result to both.
	 *
	 * @param string                $kind    Kind.
	 * @param string                $key     Key.
	 * @param array<string, string> $choices Entry id => db or file.
	 * @return array<string, mixed>|WP_Error Updated row.
	 */
	public function merge( string $kind, string $key, array $choices ) {
		$copies = $this->copies( $kind, $key );

		if ( is_wp_error( $copies ) ) {
			return $copies;
		}

		$merged = Diff::merge( $kind, $copies['db'], $copies['file'], $choices );
		$saved  = $this->save( $kind, $merged, true );

		return is_wp_error( $saved ) ? $saved : (array) $this->row_for( $kind, $key );
	}

	/**
	 * Portable copies of definitions for export.
	 *
	 * @param array<int, array{kind: string, key: string}> $wanted Kind and key pairs; empty for everything.
	 * @return array<int, array{kind: string, item: array<string, mixed>}>
	 */
	public function export( array $wanted = array() ): array {
		$out = array();

		foreach ( self::kinds() as $kind ) {
			foreach ( $this->definitions( $kind ) as $item ) {
				$match = ! $wanted;
				foreach ( $wanted as $pair ) {
					if ( $pair['kind'] === $kind && $pair['key'] === $item['key'] ) {
						$match = true;
					}
				}

				if ( $match ) {
					$out[] = array(
						'kind' => $kind,
						'item' => $this->portable( $kind, $item ),
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Sanitize and save a definition to the database, then write its file.
	 *
	 * @param string               $kind       Kind.
	 * @param array<string, mixed> $item       Definition.
	 * @param bool                 $write_file Write the file even if it changed outside CFMB.
	 * @return array<string, mixed>|WP_Error Saved definition.
	 */
	public function save( string $kind, array $item, bool $write_file = false ) {
		$this->force_write = $write_file;

		if ( 'group' === $kind ) {
			$existing = $this->groups->find( (string) ( $item['key'] ?? '' ) );
			$clean    = $this->sanitizer->sanitize( $item, $existing && 'db' === $existing->source() ? $existing : null );

			if ( ! is_wp_error( $clean ) ) {
				/**
				 * Fires before a group from a sync file, a merge or an import is
				 * saved, once it passed the sanitizer. Add-ons apply their own
				 * keys from the portable group here, before the file is written
				 * again from the saved group.
				 *
				 * @since 0.16.0
				 *
				 * @param array<string, mixed> $item  Portable group as given, with add-on keys.
				 * @param array<string, mixed> $clean Sanitized group about to be saved.
				 */
				do_action( 'cfmb/sync/saving_group', $item, $clean ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			}

			$saved    = is_wp_error( $clean ) ? $clean : $this->groups->save( $clean );
			$saved    = $saved instanceof Group ? $saved->to_array() : $saved;
		} elseif ( isset( $this->content[ $kind ] ) ) {
			$repo     = $this->content[ $kind ];
			$existing = $repo->find( (string) ( $item['key'] ?? '' ) );
			$existing = $existing && 'db' === $existing['source'] ? $existing : null;
			$clean    = $repo->schema()->sanitize( $item, $existing, $repo->names_taken( (string) ( $item['key'] ?? '' ) ) );
			$saved    = is_wp_error( $clean ) ? $clean : $repo->save( $clean );
		} else {
			$saved = new WP_Error( 'cfmb_sync_kind', __( 'Unknown definition kind.', 'cfmb' ), array( 'status' => 400 ) );
		}

		$this->force_write = false;

		return $saved;
	}

	/**
	 * Every definition of a kind (database, code and file), as arrays.
	 *
	 * @param string $kind Kind.
	 * @return array<int, array<string, mixed>>
	 */
	public function definitions( string $kind ): array {
		if ( 'group' === $kind ) {
			return array_map(
				static fn( Group $group ) => array_merge(
					$group->to_array(),
					array( 'file' => $group->file() )
				),
				$this->groups->all()
			);
		}

		return isset( $this->content[ $kind ] ) ? $this->content[ $kind ]->all() : array();
	}

	/**
	 * One definition by kind and key.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return array<string, mixed>|null
	 */
	public function definition( string $kind, string $key ): ?array {
		foreach ( $this->definitions( $kind ) as $item ) {
			if ( $item['key'] === $key ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Portable form of a definition.
	 *
	 * @param string               $kind Kind.
	 * @param array<string, mixed> $item Definition.
	 * @return array<string, mixed>
	 */
	public function portable( string $kind, array $item ): array {
		if ( 'group' === $kind ) {
			return Portable::group( $item );
		}

		return Portable::content( $item, $this->content[ $kind ]->schema() );
	}

	/**
	 * State of one definition.
	 *
	 * @param string               $kind Kind.
	 * @param array<string, mixed> $item Definition.
	 * @return array<string, mixed>
	 */
	private function row( string $kind, array $item ): array {
		$key  = (string) $item['key'];
		$file = $this->files->find( $key );

		$row = array(
			'kind'   => $kind,
			'key'    => $key,
			'title'  => (string) $item['title'],
			'name'   => (string) ( $item['name'] ?? '' ),
			'source' => (string) $item['source'],
			'status' => self::SYNCED,
			'file'   => $file ? array(
				'location' => $this->files->location_label( (string) $file['path'] ),
				'path'     => (string) $file['path'],
				'modified' => (int) $file['modified'],
			) : null,
			'note'   => $this->write_notes[ $key ] ?? '',
		);

		if ( 'code' === $item['source'] ) {
			$row['status'] = self::CODE;
			return $row;
		}

		if ( 'file' === $item['source'] ) {
			$row['status'] = self::FILE_ONLY;
			return $row;
		}

		if ( ! $file || $file['kind'] !== $kind ) {
			$row['status'] = self::NO_FILE;
			return $row;
		}

		$db_hash   = Portable::hash( $this->portable( $kind, $item ) );
		$file_hash = Portable::hash( $this->portable( $kind, $file['item'] ) );
		$base      = (string) get_post_meta( (int) $item['id'], self::META, true );

		if ( $db_hash === $file_hash ) {
			$row['status'] = self::SYNCED;
		} elseif ( $base === $db_hash ) {
			$row['status'] = self::FILE_CHANGED;
		} elseif ( $base === $file_hash ) {
			$row['status'] = self::DB_CHANGED;
		} else {
			$row['status'] = self::CONFLICT;
		}

		return $row;
	}

	/**
	 * Portable database and file copies of a definition.
	 *
	 * @param string $kind Kind.
	 * @param string $key  Key.
	 * @return array{db: array<string, mixed>, file: array<string, mixed>}|WP_Error
	 */
	private function copies( string $kind, string $key ) {
		$item = $this->definition( $kind, $key );
		$file = $this->files->find( $key );

		if ( ! $item || 'db' !== $item['source'] || ! $file || $file['kind'] !== $kind ) {
			return new WP_Error( 'cfmb_sync_no_pair', __( 'This definition needs both a database copy and a file to compare.', 'cfmb' ), array( 'status' => 404 ) );
		}

		return array(
			'db'   => $this->portable( $kind, $item ),
			'file' => $this->portable( $kind, $file['item'] ),
		);
	}
}
