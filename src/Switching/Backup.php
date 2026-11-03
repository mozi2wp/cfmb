<?php
/**
 * Original values kept by the Switch Assistant, for undo.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

defined( 'ABSPATH' ) || exit;

/**
 * Table {prefix}cfmb_switch_backup. Before the assistant changes a meta row
 * or an option, the row is copied here exactly as stored (the raw database
 * string). Undo puts every copy back, newest first.
 *
 * - restore: put this value back under the key (all rows of the key are
 *   replaced by it)
 * - rows: put these values back under the key, one row each (a JSON list;
 *   for values kept in several rows)
 * - remove: the key was created by the import; delete it
 */
class Backup {

	public const TABLE = 'cfmb_switch_backup';

	public const STORES = array(
		'post'    => array( 'postmeta', 'post_id' ),
		'term'    => array( 'termmeta', 'term_id' ),
		'user'    => array( 'usermeta', 'user_id' ),
		'comment' => array( 'commentmeta', 'comment_id' ),
	);

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  run varchar(32) NOT NULL,
  store varchar(10) NOT NULL,
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  meta_key varchar(255) NOT NULL,
  meta_value longtext NULL,
  autoload varchar(20) NULL,
  action varchar(10) NOT NULL,
  PRIMARY KEY  (id),
  KEY run (run,id)
) {$charset};"
		);
	}

	/**
	 * Keep the current rows of a meta key before changing them.
	 *
	 * @param string $run       Run ID.
	 * @param string $store     post, term, user or comment.
	 * @param int    $object_id Object ID.
	 * @param string $key       Meta key.
	 * @param string $raw       Raw stored value.
	 * @return void
	 */
	public function keep_meta( string $run, string $store, int $object_id, string $key, string $raw ): void {
		$this->insert( $run, $store, $object_id, $key, $raw, null, 'restore' );
	}

	/**
	 * Keep every row of a meta key before changing them, for values stored
	 * in several rows.
	 *
	 * @since 0.15.0
	 *
	 * @param string   $run       Run ID.
	 * @param string   $store     post, term, user or comment.
	 * @param int      $object_id Object ID.
	 * @param string   $key       Meta key.
	 * @param string[] $raws      Raw stored values, in row order.
	 * @return void
	 */
	public function keep_meta_rows( string $run, string $store, int $object_id, string $key, array $raws ): void {
		if ( 1 === count( $raws ) ) {
			$this->keep_meta( $run, $store, $object_id, $key, (string) reset( $raws ) );
			return;
		}

		$this->insert( $run, $store, $object_id, $key, (string) wp_json_encode( array_values( array_map( 'strval', $raws ) ) ), null, 'rows' );
	}

	/**
	 * Note a meta key the import created, so undo deletes it.
	 *
	 * @since 0.15.0
	 *
	 * @param string $run       Run ID.
	 * @param string $store     post, term, user or comment.
	 * @param int    $object_id Object ID.
	 * @param string $key       Meta key.
	 * @return void
	 */
	public function created_meta( string $run, string $store, int $object_id, string $key ): void {
		$this->insert( $run, $store, $object_id, $key, null, null, 'remove' );
	}

	/**
	 * Keep an option before changing or deleting it.
	 *
	 * @param string $run      Run ID.
	 * @param string $name     Option name.
	 * @param string $raw      Raw stored value.
	 * @param string $autoload Autoload flag as stored.
	 * @return void
	 */
	public function keep_option( string $run, string $name, string $raw, string $autoload ): void {
		$this->insert( $run, 'option', 0, $name, $raw, $autoload, 'restore' );
	}

	/**
	 * Note an option the import created.
	 *
	 * @param string $run  Run ID.
	 * @param string $name Option name.
	 * @return void
	 */
	public function created_option( string $run, string $name ): void {
		$this->insert( $run, 'option', 0, $name, null, null, 'remove' );
	}

	/**
	 * How many rows a run kept.
	 *
	 * @param string $run Run ID.
	 * @return int
	 */
	public function count( string $run ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE run = %s', $table, $run ) );
	}

	/**
	 * Put back up to $limit rows of a run, newest first, and delete them here.
	 *
	 * @param string $run   Run ID.
	 * @param int    $limit Rows per call.
	 * @return int Rows put back; 0 when the run is fully undone.
	 */
	public function restore( string $run, int $limit = 300 ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE run = %s ORDER BY id DESC LIMIT %d', $table, $run, $limit ), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			if ( 'option' === $row['store'] ) {
				$this->restore_option( $row );
			} elseif ( isset( self::STORES[ $row['store'] ] ) ) {
				$this->restore_meta( $row );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
			$wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) );
		}

		return count( (array) $rows );
	}

	/**
	 * Drop a run's copies without restoring them.
	 *
	 * @param string $run Run ID.
	 * @return void
	 */
	public function forget( string $run ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->delete( self::table(), array( 'run' => $run ), array( '%s' ) );
	}

	/**
	 * Put one meta key back exactly as it was.
	 *
	 * @param array<string, mixed> $row Backup row.
	 * @return void
	 */
	private function restore_meta( array $row ): void {
		global $wpdb;

		list( $suffix, $column ) = self::STORES[ $row['store'] ];

		$meta_table = $wpdb->prefix . $suffix;
		$object_id  = (int) $row['object_id'];
		$key        = (string) $row['meta_key'];

		if ( 'user' === $row['store'] ) {
			$meta_table = $wpdb->usermeta;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact restore of raw rows; caches cleared below.
		$wpdb->delete(
			$meta_table,
			array(
				$column    => $object_id,
				'meta_key' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed column.
			),
			array( '%d', '%s' )
		);

		$values = array();

		if ( 'restore' === $row['action'] && null !== $row['meta_value'] ) {
			$values = array( (string) $row['meta_value'] );
		} elseif ( 'rows' === $row['action'] ) {
			$list   = json_decode( (string) $row['meta_value'], true );
			$values = is_array( $list ) ? array_map( 'strval', $list ) : array();
		}

		foreach ( $values as $value ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Raw value as ACF stored it.
			$wpdb->insert(
				$meta_table,
				array(
					$column      => $object_id,
					'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed column.
					'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Insert, not a query.
				),
				array( '%d', '%s', '%s' )
			);
		}

		wp_cache_delete( $object_id, $row['store'] . '_meta' );
	}

	/**
	 * Put one option back exactly as it was, or remove one the import made.
	 *
	 * @param array<string, mixed> $row Backup row.
	 * @return void
	 */
	private function restore_option( array $row ): void {
		global $wpdb;

		$name = (string) $row['meta_key'];

		if ( 'remove' === $row['action'] ) {
			delete_option( $name );
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Raw value as ACF stored it; caches cleared below.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
				$name,
				(string) $row['meta_value'],
				'' !== (string) $row['autoload'] ? (string) $row['autoload'] : 'no'
			)
		);

		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Insert a backup row.
	 *
	 * @param string      $run       Run ID.
	 * @param string      $store     Store.
	 * @param int         $object_id Object ID.
	 * @param string      $key       Meta key or option name.
	 * @param string|null $raw       Raw value.
	 * @param string|null $autoload  Option autoload.
	 * @param string      $action    restore, rows or remove.
	 * @return void
	 */
	private function insert( string $run, string $store, int $object_id, string $key, ?string $raw, ?string $autoload, string $action ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table.
		$wpdb->insert(
			self::table(),
			array(
				'run'        => $run,
				'store'      => $store,
				'object_id'  => $object_id,
				'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Own table.
				'meta_value' => $raw, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Own table.
				'autoload'   => $autoload,
				'action'     => $action,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}
