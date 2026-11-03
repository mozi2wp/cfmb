<?php
/**
 * Converts ACF values to CFMB's storage, in batches.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

defined( 'ABSPATH' ) || exit;

/**
 * Works through post, term, user and comment meta, then options, a batch
 * at a time, so big sites never time out. The cursor is the last row ID
 * seen per store.
 *
 * Meta rows only change when ACF's reference row ("_{key}" holding a
 * field_… key) sits next to them, so values of other plugins that happen
 * to share a name are left alone. Every row is copied to Backup first.
 *
 * Options page values move from ACF's names ({post_id}_{key}, usually
 * options_{key}) to CFMB's (cfmb_{page}_{key}). A CFMB value already saved
 * under the new name wins and the ACF value stays where it was.
 */
class Value_Converter {

	public const BATCH = 400;

	public const STORE_ORDER = array( 'post', 'term', 'user', 'comment', 'option' );

	/**
	 * Original values.
	 *
	 * @var Backup
	 */
	private Backup $backup;

	/**
	 * Constructor.
	 *
	 * @param Backup $backup Original values.
	 */
	public function __construct( Backup $backup ) {
		$this->backup = $backup;
	}

	/**
	 * Convert the next batch.
	 *
	 * @param array<string, mixed> $run Run record: id, rules (meta), options (pages), cursor, counts.
	 * @return array<string, mixed> The run with the cursor and counts moved on; `done` true when finished.
	 */
	public function step( array $run ): array {
		$cursor = is_array( $run['cursor'] ?? null ) ? $run['cursor'] : array();
		$store  = (string) ( $cursor['store'] ?? self::STORE_ORDER[0] );
		$after  = (int) ( $cursor['after'] ?? 0 );

		if ( ! in_array( $store, self::STORE_ORDER, true ) ) {
			$run['done'] = true;
			return $run;
		}

		$result = 'option' === $store
			? $this->option_batch( $run, $after )
			: $this->meta_batch( $run, $store, $after );

		$run['counts']['converted'] = (int) ( $run['counts']['converted'] ?? 0 ) + $result['converted'];
		$run['counts']['moved']     = (int) ( $run['counts']['moved'] ?? 0 ) + $result['moved'];
		$run['counts']['kept']      = (int) ( $run['counts']['kept'] ?? 0 ) + $result['kept'];

		if ( $result['last'] > 0 ) {
			$run['cursor'] = array(
				'store' => $store,
				'after' => $result['last'],
			);
			$run['done']   = false;

			return $run;
		}

		$next          = array_search( $store, self::STORE_ORDER, true );
		$next          = false === $next ? count( self::STORE_ORDER ) : $next + 1;
		$run['cursor'] = array(
			'store' => self::STORE_ORDER[ $next ] ?? 'finished',
			'after' => 0,
		);
		$run['done']   = ! isset( self::STORE_ORDER[ $next ] );

		return $run;
	}

	/**
	 * One batch of meta rows.
	 *
	 * @param array<string, mixed> $run   Run.
	 * @param string               $store post, term, user or comment.
	 * @param int                  $after Last meta ID seen.
	 * @return array{converted: int, moved: int, kept: int, last: int}
	 */
	private function meta_batch( array $run, string $store, int $after ): array {
		global $wpdb;

		$none  = array(
			'converted' => 0,
			'moved'     => 0,
			'kept'      => 0,
			'last'      => 0,
		);
		$rules = self::rules_of( (array) ( $run['rules'] ?? array() ), array( 'list', 'date' ) );

		if ( ! $rules ) {
			return $none;
		}

		list( $suffix, $column ) = Backup::STORES[ $store ];

		$table = 'user' === $store ? $wpdb->usermeta : $wpdb->prefix . $suffix;
		$id    = 'user' === $store ? 'umeta_id' : 'meta_id';
		$where = self::name_filter( self::roots( $rules ), 'meta_key', '' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers go through %i; $where['sql'] holds name_filter() placeholders only.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT %i AS row_id, %i AS object_id, meta_key, meta_value FROM %i WHERE %i > %d AND ( {$where['sql']} ) ORDER BY %i ASC LIMIT %d", array_merge( array( $id, $column, $table, $id, $after ), $where['args'], array( $id, self::BATCH ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $rows ) {
			return $none;
		}

		$result         = $none;
		$result['last'] = (int) end( $rows )['row_id'];
		$refs           = $this->references( $table, $column, $rows );

		foreach ( $rows as $row ) {
			$kind = self::kind_for( $rules, (string) $row['meta_key'] );

			if ( '' === $kind || ! isset( $refs[ (int) $row['object_id'] . '|' . $row['meta_key'] ] ) ) {
				continue;
			}

			if ( $this->convert_meta( (string) $run['id'], $store, $table, $column, $id, $row, $kind ) ) {
				++$result['converted'];
			}
		}

		return $result;
	}

	/**
	 * Which rows have ACF reference rows beside them.
	 *
	 * @param string                           $table  Meta table.
	 * @param string                           $column Object ID column.
	 * @param array<int, array<string, mixed>> $rows   Candidate rows.
	 * @return array<string, bool> "{object_id}|{meta_key}" => true.
	 */
	private function references( string $table, string $column, array $rows ): array {
		global $wpdb;

		$keys    = array();
		$objects = array();

		foreach ( $rows as $row ) {
			$keys[ '_' . $row['meta_key'] ]     = true;
			$objects[ (int) $row['object_id'] ] = true;
		}

		$key_marks    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$object_marks = implode( ',', array_fill( 0, count( $objects ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders built from counts; identifiers go through %i.
		$found = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT %i AS object_id, meta_key FROM %i WHERE meta_key IN ({$key_marks}) AND %i IN ({$object_marks}) AND meta_value LIKE %s",
				array_merge( array( $column, $table ), array_keys( $keys ), array( $column ), array_keys( $objects ), array( 'field\_%' ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$refs = array();
		foreach ( (array) $found as $ref ) {
			$refs[ (int) $ref['object_id'] . '|' . substr( (string) $ref['meta_key'], 1 ) ] = true;
		}

		return $refs;
	}

	/**
	 * Convert one meta row.
	 *
	 * @param string               $run    Run ID.
	 * @param string               $store  Store.
	 * @param string               $table  Meta table.
	 * @param string               $column Object ID column.
	 * @param string               $id     Row ID column.
	 * @param array<string, mixed> $row    Row.
	 * @param string               $kind   list or date.
	 * @return bool Whether it changed.
	 */
	private function convert_meta( string $run, string $store, string $table, string $column, string $id, array $row, string $kind ): bool {
		global $wpdb;

		$raw       = (string) $row['meta_value'];
		$object_id = (int) $row['object_id'];
		$key       = (string) $row['meta_key'];

		if ( 'date' === $kind ) {
			if ( 1 !== preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $parts ) ) {
				return false;
			}

			$this->backup->keep_meta( $run, $store, $object_id, $key, $raw );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One row by ID; cache cleared below.
			$wpdb->update( $table, array( 'meta_value' => $parts[1] . '-' . $parts[2] . '-' . $parts[3] ), array( $id => (int) $row['row_id'] ), array( '%s' ), array( '%d' ) );
			wp_cache_delete( $object_id, $store . '_meta' );

			return true;
		}

		if ( ! is_serialized( $raw ) ) {
			return false;
		}

		$value = Acf_Reader::unserialize( $raw );
		$items = array_values( array_filter( $value, static fn( $item ) => is_scalar( $item ) && '' !== (string) $item ) );

		$this->backup->keep_meta( $run, $store, $object_id, $key, $raw );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row by ID; cache cleared below.
		$wpdb->delete( $table, array( $id => (int) $row['row_id'] ), array( '%d' ) );

		// CFMB keeps one row per item, and one empty row for a saved empty list.
		foreach ( $items ? $items : array( '' ) as $item ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Raw insert keeps the scalar exactly as ACF had it.
			$wpdb->insert(
				$table,
				array(
					$column      => $object_id,
					'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Insert.
					'meta_value' => (string) $item, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Insert.
				),
				array( '%d', '%s', '%s' )
			);
		}

		wp_cache_delete( $object_id, $store . '_meta' );

		return true;
	}

	/**
	 * One batch of options page values.
	 *
	 * @param array<string, mixed> $run   Run.
	 * @param int                  $after Last option ID seen.
	 * @return array{converted: int, moved: int, kept: int, last: int}
	 */
	private function option_batch( array $run, int $after ): array {
		global $wpdb;

		$none  = array(
			'converted' => 0,
			'moved'     => 0,
			'kept'      => 0,
			'last'      => 0,
		);
		$pages = is_array( $run['options'] ?? null ) ? $run['options'] : array();

		if ( ! $pages ) {
			return $none;
		}

		$sql  = array();
		$args = array();

		foreach ( $pages as $page ) {
			$filter = self::name_filter( self::roots( self::rules_of( (array) $page['rules'], array( 'value' ) ) ), 'option_name', (string) $page['acf_id'] . '_' );

			if ( '' !== $filter['sql'] ) {
				$sql[] = '( ' . $filter['sql'] . ' )';
				$args  = array_merge( $args, $filter['args'] );
			}
		}

		if ( ! $sql ) {
			return $none;
		}

		$where = implode( ' OR ', $sql );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built from name_filter() placeholders only; every value goes through prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_id > %d AND ( {$where} ) ORDER BY option_id ASC LIMIT %d", array_merge( array( $after ), $args, array( self::BATCH ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $rows ) {
			return $none;
		}

		$result         = $none;
		$result['last'] = (int) end( $rows )['option_id'];

		foreach ( $rows as $row ) {
			foreach ( $pages as $page ) {
				$prefix = (string) $page['acf_id'] . '_';
				$name   = (string) $row['option_name'];

				if ( 0 !== strpos( $name, $prefix ) ) {
					continue;
				}

				$key   = substr( $name, strlen( $prefix ) );
				$rules = (array) $page['rules'];

				if ( '' === self::kind_for( self::rules_of( $rules, array( 'value' ) ), $key ) ) {
					continue;
				}

				$is_date = 'date' === self::kind_for( self::rules_of( $rules, array( 'date' ) ), $key );
				$moved   = $this->move_option( (string) $run['id'], $row, 'cfmb_' . (string) $page['page'] . '_' . $key, $is_date );

				++$result[ $moved ? 'moved' : 'kept' ];
				break;
			}
		}

		return $result;
	}

	/**
	 * Move one option to its CFMB name.
	 *
	 * @param string               $run     Run ID.
	 * @param array<string, mixed> $row     Option row.
	 * @param string               $target  New option name.
	 * @param bool                 $is_date Whether to turn Ymd into Y-m-d.
	 * @return bool False when a CFMB value was already there.
	 */
	private function move_option( string $run, array $row, string $target, bool $is_date ): bool {
		$missing = new \stdClass();

		if ( get_option( $target, $missing ) !== $missing ) {
			return false;
		}

		$raw   = (string) $row['option_value'];
		$value = is_serialized( $raw ) ? Acf_Reader::unserialize( $raw ) : $raw;

		if ( $is_date && is_string( $value ) && 1 === preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $parts ) ) {
			$value = $parts[1] . '-' . $parts[2] . '-' . $parts[3];
		}

		$this->backup->keep_option( $run, (string) $row['option_name'], $raw, (string) $row['autoload'] );
		$this->backup->created_option( $run, $target );

		add_option( $target, $value, '', false );
		delete_option( (string) $row['option_name'] );

		return true;
	}

	/**
	 * Rules of some kinds.
	 *
	 * @param array<int, array{pattern: string, kind: string}> $rules Rules.
	 * @param string[]                                         $kinds Kinds.
	 * @return array<int, array{pattern: string, kind: string}>
	 */
	private static function rules_of( array $rules, array $kinds ): array {
		return array_values( array_filter( $rules, static fn( $rule ): bool => is_array( $rule ) && in_array( $rule['kind'] ?? '', $kinds, true ) ) );
	}

	/**
	 * The kind of the first rule matching a key.
	 *
	 * @param array<int, array{pattern: string, kind: string}> $rules Rules.
	 * @param string                                           $key   Flattened key.
	 * @return string Kind, or empty.
	 */
	private static function kind_for( array $rules, string $key ): string {
		foreach ( $rules as $rule ) {
			if ( 1 === preg_match( (string) $rule['pattern'], $key ) ) {
				return (string) $rule['kind'];
			}
		}

		return '';
	}

	/**
	 * Top-level field names the rules start with, so SQL can narrow rows.
	 *
	 * @param array<int, array{pattern: string, kind: string}> $rules Rules.
	 * @return string[]
	 */
	private static function roots( array $rules ): array {
		$roots = array();

		foreach ( $rules as $rule ) {
			if ( 1 === preg_match( '/^\/\^([a-z0-9_]+)/', (string) $rule['pattern'], $match ) ) {
				// The regex starts with the top-level name; stop at the first regex part.
				$roots[ rtrim( $match[1], '_' ) ] = true;
			}
		}

		return array_keys( $roots );
	}

	/**
	 * SQL matching names equal to, or starting with "{root}_", for each root.
	 *
	 * @param string[] $roots  Top-level names.
	 * @param string   $column Column.
	 * @param string   $prefix Prefix before the root (options: "options_").
	 * @return array{sql: string, args: string[]}
	 */
	private static function name_filter( array $roots, string $column, string $prefix ): array {
		global $wpdb;

		$parts = array();
		$args  = array();

		foreach ( $roots as $root ) {
			$parts[] = "{$column} = %s OR {$column} LIKE %s";
			$args[]  = $prefix . $root;
			$args[]  = $wpdb->esc_like( $prefix . $root . '_' ) . '%';
		}

		return array(
			'sql'  => implode( ' OR ', $parts ),
			'args' => $args,
		);
	}
}
