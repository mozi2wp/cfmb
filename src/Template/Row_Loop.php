<?php
/**
 * State behind cfmb_have_rows() and friends.
 *
 * @package CFMB
 */

namespace CFMB\Template;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A stack of open row loops. The innermost loop is on top; nested loops
 * (a Repeater inside a Stacks row) push onto it and pop when they end.
 */
final class Row_Loop {

	/**
	 * Open loops, innermost last. Each: name, ref, field, rows, index.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $loops = array();

	/**
	 * Innermost loop.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function top(): ?array {
		return self::$loops ? self::$loops[ count( self::$loops ) - 1 ] : null;
	}

	/**
	 * Open a loop. The first cfmb_the_row() moves to row 0.
	 *
	 * @param string                   $name  Field name used in cfmb_have_rows().
	 * @param string                   $ref   Object reference string.
	 * @param Field|null               $field Field definition, when known.
	 * @param array<int, array<mixed>> $rows  Formatted rows.
	 * @return void
	 */
	public static function push( string $name, string $ref, ?Field $field, array $rows ): void {
		self::$loops[] = array(
			'name'  => $name,
			'ref'   => $ref,
			'field' => $field,
			'rows'  => array_values( $rows ),
			'index' => -1,
		);
	}

	/**
	 * Close the innermost loop.
	 *
	 * @return void
	 */
	public static function pop(): void {
		array_pop( self::$loops );
	}

	/**
	 * Move the innermost loop to its next row.
	 *
	 * @return array<string, mixed> The row, or an empty array past the end.
	 */
	public static function advance(): array {
		$last = count( self::$loops ) - 1;

		if ( $last < 0 ) {
			return array();
		}

		++self::$loops[ $last ]['index'];

		return self::current();
	}

	/**
	 * Current row of the innermost loop.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array {
		$top = self::top();

		if ( ! $top || $top['index'] < 0 ) {
			return array();
		}

		$row = $top['rows'][ $top['index'] ] ?? array();

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Definition of a sub-field in the current row, when known.
	 *
	 * @param string $name Sub-field name.
	 * @return Field|null
	 */
	public static function sub_field( string $name ): ?Field {
		$top = self::top();

		if ( ! $top || ! $top['field'] instanceof Field ) {
			return null;
		}

		$field  = $top['field'];
		$fields = $field->sub_fields();

		if ( 'stacks' === $field->type() ) {
			$layout = $field->layout( (string) ( self::current()['_layout'] ?? '' ) );
			$fields = $layout ? $layout['fields'] : array();
		}

		foreach ( $fields as $sub ) {
			if ( $sub->name() === $name ) {
				return $sub;
			}
		}

		return null;
	}
}
