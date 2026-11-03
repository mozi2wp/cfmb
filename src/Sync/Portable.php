<?php
/**
 * Comparable form of a definition.
 *
 * @package CFMB
 */

namespace CFMB\Sync;

use CFMB\Content\Content_Schema;
use CFMB\Schema\Field;
use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * Brings a definition from the database, a file or code into one shape,
 * so two copies hash the same when they mean the same: defaults filled in,
 * runtime keys (id, source, file) dropped, object keys sorted.
 */
final class Portable {

	/**
	 * Portable form of a Field Group.
	 *
	 * @param array<string, mixed> $group Group array.
	 * @return array<string, mixed>
	 */
	public static function group( array $group ): array {
		$array = ( new Group( array_merge( $group, array( 'source' => 'db' ) ) ) )->to_array();

		unset( $array['id'], $array['source'] );

		// Block settings only count while the group is a Field Block, so groups saved before blocks existed hash the same.
		if ( empty( $array['settings']['block']['enabled'] ) ) {
			unset( $array['settings']['block'] );
		}

		$array['fields'] = self::fields( (array) $array['fields'] );

		/**
		 * Filters the portable form of a Field Group: what is written to its
		 * sync file and compared with it. Add-ons can add their own top-level
		 * keys for settings that belong to the group; leave them out while they
		 * hold defaults, so files written without the add-on hash the same.
		 *
		 * @since 0.16.0
		 *
		 * @param array<string, mixed> $array Portable group.
		 * @param array<string, mixed> $group Group as given: from the database, a file or code.
		 */
		$filtered = apply_filters( 'cfmb/sync/portable_group', $array, $group ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return is_array( $filtered ) ? $filtered : $array;
	}

	/**
	 * Portable form of a post type, taxonomy or options page.
	 *
	 * @param array<string, mixed> $item   Definition.
	 * @param Content_Schema       $schema Its schema.
	 * @return array<string, mixed>
	 */
	public static function content( array $item, Content_Schema $schema ): array {
		return $schema->normalize( $item );
	}

	/**
	 * Hash of a portable definition.
	 *
	 * @param array<string, mixed> $portable Portable definition.
	 * @return string
	 */
	public static function hash( array $portable ): string {
		return md5( (string) wp_json_encode( self::sorted( $portable ) ) );
	}

	/**
	 * Field definitions with defaults filled in, at every depth.
	 *
	 * @param array<int, mixed> $fields Field definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private static function fields( array $fields ): array {
		$out = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field          = array_merge( Field::defaults(), $field );
			$field['admin'] = array_merge( Field::defaults()['admin'], (array) $field['admin'] );
			unset( $field['base_name'] );

			if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$field['fields'] = self::fields( $field['fields'] );
			}

			if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $index => $layout ) {
					if ( is_array( $layout ) ) {
						$field['layouts'][ $index ]['fields'] = self::fields( (array) ( $layout['fields'] ?? array() ) );
					}
				}
			}

			$out[] = $field;
		}

		return $out;
	}

	/**
	 * Sort object keys at every depth; lists keep their order.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function sorted( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$is_list = array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );

		if ( ! $is_list ) {
			ksort( $value );
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sorted( $item );
		}

		return $value;
	}
}
