<?php
/**
 * Differences between the database and file copy of a definition.
 *
 * @package CFMB
 */

namespace CFMB\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Lists what differs between two portable copies, entry by entry, and
 * merges them from a choice per entry.
 *
 * Field Groups compare their title, state, order, location, settings,
 * field order and each top-level field by key. Post types, taxonomies and
 * options pages compare their title, name, state and each setting.
 */
final class Diff {

	/**
	 * Entries that differ.
	 *
	 * @param string               $kind Definition kind.
	 * @param array<string, mixed> $db   Portable database copy.
	 * @param array<string, mixed> $file Portable file copy.
	 * @return array<int, array<string, mixed>> Each: id, label, status (changed, only_db, only_file), db, file.
	 */
	public static function entries( string $kind, array $db, array $file ): array {
		$entries = array();
		$top     = 'group' === $kind
			? array(
				'title'    => __( 'Title', 'custom-fields-meta-box' ),
				'active'   => __( 'Active', 'custom-fields-meta-box' ),
				'order'    => __( 'Order', 'custom-fields-meta-box' ),
				'location' => __( 'Location rules', 'custom-fields-meta-box' ),
				'settings' => __( 'Group settings', 'custom-fields-meta-box' ),
			)
			: array(
				'title'  => __( 'Title', 'custom-fields-meta-box' ),
				'name'   => __( 'Name', 'custom-fields-meta-box' ),
				'active' => __( 'Active', 'custom-fields-meta-box' ),
			);

		foreach ( $top as $key => $label ) {
			self::compare( $entries, $key, $label, $db[ $key ] ?? null, $file[ $key ] ?? null );
		}

		if ( 'group' === $kind ) {
			// Keys add-ons put in the portable group (cfmb/sync/portable_group).
			$known = array_merge( array_keys( $top ), array( 'key', 'fields', 'modified', 'kind' ) );
			$extra = array_diff( array_unique( array_merge( array_keys( $db ), array_keys( $file ) ) ), $known );

			foreach ( $extra as $key ) {
				$key = (string) $key;

				/**
				 * Filters the label of an add-on's key in the sync diff.
				 *
				 * @since 0.16.0
				 *
				 * @param string $label Label; the key itself by default.
				 * @param string $key   Top-level key.
				 */
				$label = (string) apply_filters( 'cfmb/sync/diff_label', $key, $key ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

				self::compare( $entries, $key, $label, $db[ $key ] ?? null, $file[ $key ] ?? null );
			}
		}

		if ( 'group' !== $kind ) {
			$names = array_unique( array_merge( array_keys( (array) ( $db['settings'] ?? array() ) ), array_keys( (array) ( $file['settings'] ?? array() ) ) ) );

			foreach ( $names as $name ) {
				/* translators: %s: setting name */
				self::compare( $entries, 'settings.' . $name, sprintf( __( 'Setting: %s', 'custom-fields-meta-box' ), $name ), $db['settings'][ $name ] ?? null, $file['settings'][ $name ] ?? null );
			}

			return $entries;
		}

		$db_fields   = self::by_key( (array) ( $db['fields'] ?? array() ) );
		$file_fields = self::by_key( (array) ( $file['fields'] ?? array() ) );
		$common      = array_values( array_intersect( array_keys( $db_fields ), array_keys( $file_fields ) ) );
		$file_common = array_values( array_intersect( array_keys( $file_fields ), array_keys( $db_fields ) ) );

		if ( $common !== $file_common ) {
			self::compare( $entries, 'fields.order', __( 'Field order', 'custom-fields-meta-box' ), $common, $file_common );
		}

		foreach ( array_unique( array_merge( array_keys( $db_fields ), array_keys( $file_fields ) ) ) as $key ) {
			$field = $db_fields[ $key ] ?? $file_fields[ $key ];
			$label = sprintf(
				/* translators: 1: field label, 2: field name */
				__( 'Field: %1$s (%2$s)', 'custom-fields-meta-box' ),
				(string) ( $field['label'] ?? '' ),
				'' !== (string) ( $field['name'] ?? '' ) ? (string) $field['name'] : (string) ( $field['type'] ?? '' )
			);

			self::compare( $entries, 'field:' . $key, $label, $db_fields[ $key ] ?? null, $file_fields[ $key ] ?? null );
		}

		return $entries;
	}

	/**
	 * Merge two copies. Entries without a choice take the file copy.
	 *
	 * @param string                $kind    Definition kind.
	 * @param array<string, mixed>  $db      Portable database copy.
	 * @param array<string, mixed>  $file    Portable file copy.
	 * @param array<string, string> $choices Entry id => "db" or "file".
	 * @return array<string, mixed> Merged definition, still to be sanitized.
	 */
	public static function merge( string $kind, array $db, array $file, array $choices ): array {
		$merged = $db;
		$pick   = static fn( string $id ): string => 'db' === ( $choices[ $id ] ?? 'file' ) ? 'db' : 'file';

		foreach ( self::entries( $kind, $db, $file ) as $entry ) {
			$id = $entry['id'];

			if ( 'db' === $pick( $id ) || 'fields.order' === $id || 0 === strpos( $id, 'field:' ) ) {
				continue;
			}

			if ( 0 === strpos( $id, 'settings.' ) ) {
				$name = substr( $id, 9 );

				if ( null === $entry['file'] ) {
					unset( $merged['settings'][ $name ] );
				} else {
					$merged['settings'][ $name ] = $entry['file'];
				}
				continue;
			}

			$merged[ $id ] = $entry['file'];
		}

		if ( 'group' === $kind ) {
			$merged['fields'] = self::merge_fields( (array) ( $db['fields'] ?? array() ), (array) ( $file['fields'] ?? array() ), $pick );
		}

		return $merged;
	}

	/**
	 * Merge top-level fields by key, following the chosen field order.
	 *
	 * @param array<int, array<string, mixed>> $db_list   Database fields.
	 * @param array<int, array<string, mixed>> $file_list File fields.
	 * @param callable                         $pick      Entry id => "db" or "file".
	 * @return array<int, array<string, mixed>>
	 */
	private static function merge_fields( array $db_list, array $file_list, callable $pick ): array {
		$db_fields   = self::by_key( $db_list );
		$file_fields = self::by_key( $file_list );
		$order       = 'file' === $pick( 'fields.order' ) ? array_keys( $file_fields ) : array_keys( $db_fields );
		$other       = 'file' === $pick( 'fields.order' ) ? array_keys( $db_fields ) : array_keys( $file_fields );

		// Fields only in the other copy go where they sit relative to the field before them.
		foreach ( $other as $index => $key ) {
			if ( in_array( $key, $order, true ) ) {
				continue;
			}

			$before = $index > 0 ? array_search( $other[ $index - 1 ], $order, true ) : false;
			array_splice( $order, false === $before ? 0 : $before + 1, 0, array( $key ) );
		}

		$fields = array();

		foreach ( $order as $key ) {
			$chosen = 'db' === $pick( 'field:' . $key ) ? ( $db_fields[ $key ] ?? null ) : ( $file_fields[ $key ] ?? null );

			if ( null !== $chosen ) {
				$fields[] = $chosen;
			}
		}

		return $fields;
	}

	/**
	 * Add an entry when two values differ.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries. Updated.
	 * @param string                           $id      Entry id.
	 * @param string                           $label   Label.
	 * @param mixed                            $db      Database value, null when missing.
	 * @param mixed                            $file    File value, null when missing.
	 * @return void
	 */
	private static function compare( array &$entries, string $id, string $label, $db, $file ): void {
		if ( wp_json_encode( Portable::sorted( $db ) ) === wp_json_encode( Portable::sorted( $file ) ) ) {
			return;
		}

		$status = 'changed';
		if ( null === $file ) {
			$status = 'only_db';
		} elseif ( null === $db ) {
			$status = 'only_file';
		}

		$entries[] = array(
			'id'     => $id,
			'label'  => $label,
			'status' => $status,
			'db'     => $db,
			'file'   => $file,
		);
	}

	/**
	 * Fields keyed by their key, in order.
	 *
	 * @param array<int, mixed> $fields Fields.
	 * @return array<string, array<string, mixed>>
	 */
	private static function by_key( array $fields ): array {
		$out = array();

		foreach ( $fields as $field ) {
			if ( is_array( $field ) && is_string( $field['key'] ?? null ) ) {
				$out[ $field['key'] ] = $field;
			}
		}

		return $out;
	}
}
