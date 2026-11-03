<?php
/**
 * The links between connected objects.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

defined( 'ABSPATH' ) || exit;

/**
 * Table {prefix}cfmb_connections, one row per link:
 *
 * - connection: connection ID
 * - from_id, to_id: object IDs on each side
 * - from_order: position of to_id in from_id's list
 * - to_order: position of from_id in to_id's list
 *
 * Each side has its own index, so listing the items linked to an object is
 * one indexed query in either direction. Rows have an `id` (the link ID)
 * that add-ons can keep their own data against. Whenever rows are deleted,
 * `cfmb/connections/unlinked` fires with their IDs.
 *
 * Reads are cached per object in the object cache group cfmb_connections.
 */
class Connection_Store {

	public const TABLE = 'cfmb_connections';

	public const CACHE_GROUP = 'cfmb_connections';

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

		// dbDelta wants two spaces after PRIMARY KEY and one index per line.
		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  connection varchar(64) NOT NULL,
  from_id bigint(20) unsigned NOT NULL,
  to_id bigint(20) unsigned NOT NULL,
  from_order int(11) unsigned NOT NULL DEFAULT 0,
  to_order int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY link (connection,from_id,to_id),
  KEY from_side (connection,from_id,from_order),
  KEY to_side (connection,to_id,to_order)
) {$charset};"
		);
	}

	/**
	 * IDs linked to an object, in that object's order.
	 *
	 * @param string $connection Connection ID.
	 * @param string $side       Side the object is on: from or to.
	 * @param int    $id         Object ID.
	 * @return int[]
	 */
	public function ids( string $connection, string $side, int $id ): array {
		global $wpdb;

		if ( $id <= 0 ) {
			return array();
		}

		$cache_key = $this->cache_key( $connection, $side, $id );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		if ( 'to' === $side ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table; cached above.
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT from_id FROM {$table} WHERE connection = %s AND to_id = %d ORDER BY to_order ASC, id ASC", $connection, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table; cached above.
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT to_id FROM {$table} WHERE connection = %s AND from_id = %d ORDER BY from_order ASC, id ASC", $connection, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		}

		$ids = array_map( 'intval', is_array( $rows ) ? $rows : array() );
		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP );

		return $ids;
	}

	/**
	 * Link IDs of an object's links, keyed by the linked item's ID.
	 *
	 * @since 0.12.0
	 *
	 * @param string $connection Connection ID.
	 * @param string $side       Side the object is on: from or to.
	 * @param int    $id         Object ID.
	 * @return array<int, int> Linked item ID => link ID.
	 */
	public function link_ids( string $connection, string $side, int $id ): array {
		global $wpdb;

		if ( $id <= 0 ) {
			return array();
		}

		$cache_key = $this->cache_key( $connection, $side, $id, 'links' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = self::table();

		if ( 'to' === $side ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table; cached above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, from_id AS other FROM {$table} WHERE connection = %s AND to_id = %d", $connection, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table; cached above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, to_id AS other FROM {$table} WHERE connection = %s AND from_id = %d", $connection, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		}

		$map = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ (int) $row['other'] ] = (int) $row['id'];
		}

		wp_cache_set( $cache_key, $map, self::CACHE_GROUP );

		return $map;
	}

	/**
	 * Full rows of links by link ID.
	 *
	 * @since 0.12.0
	 *
	 * @param int[] $link_ids Link IDs.
	 * @return array<int, array{id: int, connection: string, from_id: int, to_id: int, from_order: int, to_order: int}> Keyed by link ID.
	 */
	public function links( array $link_ids ): array {
		global $wpdb;

		$link_ids = array_values( array_unique( array_filter( array_map( 'intval', $link_ids ), static fn( int $item ) => $item > 0 ) ) );

		if ( ! $link_ids ) {
			return array();
		}

		$table = self::table();
		$found = array();

		foreach ( array_chunk( $link_ids, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own table; placeholders built above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, connection, from_id, to_id, from_order, to_order FROM {$table} WHERE id IN ({$placeholders})", $chunk ), ARRAY_A );

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$found[ (int) $row['id'] ] = array(
					'id'         => (int) $row['id'],
					'connection' => (string) $row['connection'],
					'from_id'    => (int) $row['from_id'],
					'to_id'      => (int) $row['to_id'],
					'from_order' => (int) $row['from_order'],
					'to_order'   => (int) $row['to_order'],
				);
			}
		}

		return $found;
	}

	/**
	 * Delete links by link ID, whatever their connection. Used to clean up
	 * links to items that no longer exist.
	 *
	 * @since 0.12.0
	 *
	 * @param int[] $link_ids Link IDs.
	 * @return int Links deleted.
	 */
	public function remove_links( array $link_ids ): int {
		global $wpdb;

		$rows = $this->links( $link_ids );

		if ( ! $rows ) {
			return 0;
		}

		$table   = self::table();
		$deleted = 0;
		$by_name = array();

		foreach ( array_chunk( array_keys( $rows ), 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own table; whole cache group invalidated below.
			$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $chunk ) );
		}

		foreach ( $rows as $row ) {
			$by_name[ $row['connection'] ][] = $row['id'];
		}

		$this->flush();

		foreach ( $by_name as $name => $ids ) {
			$this->announce_unlinked( (string) $name, $ids );
		}

		return $deleted;
	}

	/**
	 * Put links back with their original link IDs and order, for undo.
	 * Links that exist again in the meantime are skipped.
	 *
	 * @since 0.12.0
	 *
	 * @param array<int, array<string, mixed>> $rows Rows as links() returns them.
	 * @return int Links restored.
	 */
	public function restore_links( array $rows ): int {
		global $wpdb;

		$table    = self::table();
		$restored = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['connection'] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; cache flushed below.
			$restored += (int) $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (id, connection, from_id, to_id, from_order, to_order) VALUES (%d, %s, %d, %d, %d, %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					(int) $row['id'],
					substr( sanitize_key( (string) $row['connection'] ), 0, 64 ),
					(int) $row['from_id'],
					(int) $row['to_id'],
					max( 0, (int) ( $row['from_order'] ?? 0 ) ),
					max( 0, (int) ( $row['to_order'] ?? 0 ) )
				)
			);
		}

		$this->flush();

		return $restored;
	}

	/**
	 * Replace the items an object links to, keeping the order given.
	 *
	 * Links the other side's limit doesn't allow (one-to-many and so on) are
	 * moved: an item can only belong to one object, so its old link goes.
	 *
	 * @param Connection $connection Connection.
	 * @param string     $side       Side the object is on.
	 * @param int        $id         Object ID.
	 * @param int[]      $ids        Linked IDs, in order. Already checked.
	 * @return bool
	 */
	public function replace( Connection $connection, string $side, int $id, array $ids ): bool {
		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		$name  = $connection->name();
		$other = Connection::other( $side );
		$ids   = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $item ) => $item > 0 ) ) );
		$max   = $connection->max( $side );

		if ( $max > 0 ) {
			$ids = array_slice( $ids, 0, $max );
		}

		$current = $this->ids( $name, $side, $id );
		$links   = $this->link_ids( $name, $side, $id );
		$removed = array();
		$table   = self::table();
		$own_col = 'from' === $side ? 'from_id' : 'to_id';
		$far_col = 'from' === $side ? 'to_id' : 'from_id';
		$own_ord = 'from' === $side ? 'from_order' : 'to_order';
		$touched = array( array( $id, $side ) );

		foreach ( array_diff( $current, $ids ) as $gone ) {
			if ( isset( $links[ $gone ] ) ) {
				$removed[] = $links[ $gone ];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table; caches cleared below.
			$wpdb->delete(
				$table,
				array(
					'connection' => $name,
					$own_col     => $id,
					$far_col     => $gone,
				),
				array( '%s', '%d', '%d' )
			);
			$touched[] = array( $gone, $other );
		}

		$other_max = $connection->max( $other );

		foreach ( $ids as $position => $linked ) {
			if ( in_array( $linked, $current, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
				$wpdb->update(
					$table,
					array( $own_ord => $position ),
					array(
						'connection' => $name,
						$own_col     => $id,
						$far_col     => $linked,
					),
					array( '%d' ),
					array( '%s', '%d', '%d' )
				);
				continue;
			}

			if ( $other_max > 0 ) {
				foreach ( $this->ids( $name, $other, $linked ) as $previous ) {
					$this->unlink( $connection, $side, $previous, $linked );
					$touched[] = array( $previous, $side );
				}
			}

			$this->insert( $connection, $side, $id, $linked, $position );
			$touched[] = array( $linked, $other );
		}

		$this->forget( $name, $touched );
		$this->announce_unlinked( $name, $removed );

		/**
		 * Fires after an object's links were replaced.
		 *
		 * @param Connection $connection Connection.
		 * @param string     $side       Side the object is on.
		 * @param int        $id         Object ID.
		 * @param int[]      $ids        Linked IDs now, in order.
		 */
		do_action( 'cfmb/connections/saved', $connection, $side, $id, $ids ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return true;
	}

	/**
	 * Add one link at the end of both lists. Limits apply as in replace().
	 *
	 * @param Connection $connection Connection.
	 * @param int        $from       "From" object ID.
	 * @param int        $to         "To" object ID.
	 * @return bool
	 */
	public function connect( Connection $connection, int $from, int $to ): bool {
		if ( $from <= 0 || $to <= 0 ) {
			return false;
		}

		$ids = $this->ids( $connection->name(), 'from', $from );

		if ( in_array( $to, $ids, true ) ) {
			return true;
		}

		$ids[] = $to;

		if ( $connection->max( 'from' ) > 0 ) {
			$ids = array( $to );
		}

		return $this->replace( $connection, 'from', $from, $ids );
	}

	/**
	 * Remove one link.
	 *
	 * @param Connection $connection Connection.
	 * @param int        $from       "From" object ID.
	 * @param int        $to         "To" object ID.
	 * @return bool
	 */
	public function disconnect( Connection $connection, int $from, int $to ): bool {
		$done = $this->unlink( $connection, 'from', $from, $to );

		$this->forget(
			$connection->name(),
			array(
				array( $from, 'from' ),
				array( $to, 'to' ),
			)
		);

		return $done;
	}

	/**
	 * Move every link of a connection to a new ID.
	 *
	 * @param string $from Old connection ID.
	 * @param string $to   New connection ID.
	 * @return int Rows moved.
	 */
	public function rename( string $from, string $to ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table; whole cache group invalidated below.
		$moved = $wpdb->update( self::table(), array( 'connection' => $to ), array( 'connection' => $from ), array( '%s' ), array( '%s' ) );
		$this->flush();

		return (int) $moved;
	}

	/**
	 * How many links a connection has.
	 *
	 * @param string $connection Connection ID.
	 * @return int
	 */
	public function count( string $connection ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table; admin-only count.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE connection = %s", $connection ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	}

	/**
	 * Drop every cached read.
	 *
	 * @return void
	 */
	public function flush(): void {
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
			return;
		}

		wp_cache_set_last_changed( self::CACHE_GROUP );
	}

	/**
	 * Insert a link with its position on the given side and last on the other.
	 *
	 * @param Connection $connection Connection.
	 * @param string     $side       Side of $id.
	 * @param int        $id         Object ID.
	 * @param int        $linked     Linked ID.
	 * @param int        $position   Position in $id's list.
	 * @return void
	 */
	private function insert( Connection $connection, string $side, int $id, int $linked, int $position ): void {
		global $wpdb;

		$other     = Connection::other( $side );
		$far_count = count( $this->ids( $connection->name(), $other, $linked ) );
		$from      = 'from' === $side ? $id : $linked;
		$to        = 'from' === $side ? $linked : $id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table; caches cleared by the caller.
		$wpdb->insert(
			self::table(),
			array(
				'connection' => $connection->name(),
				'from_id'    => $from,
				'to_id'      => $to,
				'from_order' => 'from' === $side ? $position : $far_count,
				'to_order'   => 'to' === $side ? $position : $far_count,
			),
			array( '%s', '%d', '%d', '%d', '%d' )
		);

		$this->drop( $connection->name(), $other, $linked );
	}

	/**
	 * Delete one link, seen from a side.
	 *
	 * @param Connection $connection Connection.
	 * @param string     $side       Side of $id.
	 * @param int        $id         Object ID.
	 * @param int        $linked     Linked ID.
	 * @return bool
	 */
	private function unlink( Connection $connection, string $side, int $id, int $linked ): bool {
		global $wpdb;

		$link_id = $this->link_ids( $connection->name(), $side, $id )[ $linked ] ?? 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table; caches cleared by the caller.
		$deleted = $wpdb->delete(
			self::table(),
			array(
				'connection' => $connection->name(),
				'from_id'    => 'from' === $side ? $id : $linked,
				'to_id'      => 'from' === $side ? $linked : $id,
			),
			array( '%s', '%d', '%d' )
		);

		$this->drop( $connection->name(), $side, $id );
		$this->drop( $connection->name(), Connection::other( $side ), $linked );

		if ( $deleted && $link_id ) {
			$this->announce_unlinked( $connection->name(), array( $link_id ) );
		}

		return (bool) $deleted;
	}

	/**
	 * Clear cached reads for objects.
	 *
	 * @param string                               $connection Connection ID.
	 * @param array<int, array{0: int, 1: string}> $objects    Pairs of object ID and side.
	 * @return void
	 */
	private function forget( string $connection, array $objects ): void {
		foreach ( $objects as $object ) {
			$this->drop( $connection, $object[1], (int) $object[0] );
		}
	}

	/**
	 * Clear one object's cached lists.
	 *
	 * @param string $connection Connection ID.
	 * @param string $side       Side.
	 * @param int    $id         Object ID.
	 * @return void
	 */
	private function drop( string $connection, string $side, int $id ): void {
		wp_cache_delete( $this->cache_key( $connection, $side, $id ), self::CACHE_GROUP );
		wp_cache_delete( $this->cache_key( $connection, $side, $id, 'links' ), self::CACHE_GROUP );
	}

	/**
	 * Tell add-ons which links were deleted.
	 *
	 * @param string $connection Connection ID.
	 * @param int[]  $link_ids   Deleted link IDs.
	 * @return void
	 */
	private function announce_unlinked( string $connection, array $link_ids ): void {
		$link_ids = array_values( array_filter( array_map( 'intval', $link_ids ) ) );

		if ( ! $link_ids ) {
			return;
		}

		/**
		 * Fires after links were deleted: unlinked in a box, moved by a
		 * limit, removed with cfmb_disconnect() or cleaned up.
		 *
		 * @since 0.12.0
		 *
		 * @param string $connection Connection ID.
		 * @param int[]  $link_ids   Link IDs that no longer exist.
		 */
		do_action( 'cfmb/connections/unlinked', $connection, $link_ids ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Cache key for one object's list.
	 *
	 * @param string $connection Connection ID.
	 * @param string $side       Side.
	 * @param int    $id         Object ID.
	 * @param string $list       ids or links.
	 * @return string
	 */
	private function cache_key( string $connection, string $side, int $id, string $list = 'ids' ): string {
		return $connection . ':' . ( 'to' === $side ? 't' : 'f' ) . ':' . $id . ( 'links' === $list ? ':l' : '' ) . ':' . wp_cache_get_last_changed( self::CACHE_GROUP );
	}
}
