<?php
/**
 * The loop item a builder is rendering right now.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

defined( 'ABSPATH' ) || exit;

/**
 * A stack of open Field Loop items, innermost last. A Repeater loop inside
 * a Field Loop template pushes onto it and pops when that item is done.
 *
 * Each item is an array made by Loop_Items: kind (row, image or post),
 * position, count and the kind's own keys (see Loop_Items::collect()).
 */
final class Loop_Context {

	/**
	 * Open items, innermost last.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $items = array();

	/**
	 * Open an item.
	 *
	 * @param array<string, mixed> $item Loop item.
	 * @return void
	 */
	public static function push( array $item ): void {
		self::$items[] = $item;
	}

	/**
	 * Close the innermost item.
	 *
	 * @return void
	 */
	public static function pop(): void {
		array_pop( self::$items );
	}

	/**
	 * Innermost item.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function current(): ?array {
		return self::$items ? self::$items[ count( self::$items ) - 1 ] : null;
	}

	/**
	 * Innermost item of a kind, looking outwards.
	 *
	 * @param string $kind row, image or post.
	 * @return array<string, mixed>|null
	 */
	public static function nearest( string $kind ): ?array {
		for ( $i = count( self::$items ) - 1; $i >= 0; $i-- ) {
			if ( $kind === self::$items[ $i ]['kind'] ) {
				return self::$items[ $i ];
			}
		}

		return null;
	}

	/**
	 * Open items, outermost first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		return self::$items;
	}

	/**
	 * How many items are open.
	 *
	 * @return int
	 */
	public static function depth(): int {
		return count( self::$items );
	}
}
