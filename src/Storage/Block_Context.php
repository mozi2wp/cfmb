<?php
/**
 * The Field Block being rendered.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * A stack of Field Block instances being rendered, so the template API can
 * default to the current block. A stack, not a single value, because a
 * Field Block's template may render another Field Block.
 */
final class Block_Context {

	/**
	 * Instances being rendered, innermost last.
	 *
	 * @var array<int, array{ref: Object_Ref, block: array<string, mixed>}>
	 */
	private static array $stack = array();

	/**
	 * Enter a block.
	 *
	 * @param Object_Ref           $ref   Block instance.
	 * @param array<string, mixed> $block Block details passed to its template.
	 * @return void
	 */
	public static function push( Object_Ref $ref, array $block ): void {
		self::$stack[] = array(
			'ref'   => $ref,
			'block' => $block,
		);
	}

	/**
	 * Leave the innermost block.
	 *
	 * @return void
	 */
	public static function pop(): void {
		array_pop( self::$stack );
	}

	/**
	 * Reference to the innermost block, or null outside Field Blocks.
	 *
	 * @return Object_Ref|null
	 */
	public static function current(): ?Object_Ref {
		$top = end( self::$stack );

		return $top ? $top['ref'] : null;
	}

	/**
	 * Details of the innermost block, or null outside Field Blocks.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function block(): ?array {
		$top = end( self::$stack );

		return $top ? $top['block'] : null;
	}
}
