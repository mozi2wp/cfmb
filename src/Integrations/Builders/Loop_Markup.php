<?php
/**
 * Wrapper markup shared by Field Loop modules.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

defined( 'ABSPATH' ) || exit;

/**
 * A Field Loop prints its items in a CSS grid. Builders with their own
 * responsive column controls (Elementor) style the wrapper themselves.
 */
final class Loop_Markup {

	/**
	 * Grid wrapper around rendered items.
	 *
	 * @param string $items   Rendered items, already escaped by the builder.
	 * @param int    $columns Columns, 1 to 6.
	 * @param string $gap     CSS length; anything else falls back to 20px.
	 * @param string $class   Extra wrapper class.
	 * @return string
	 */
	public static function grid( string $items, int $columns, string $gap, string $class = '' ): string {
		$columns = max( 1, min( 6, $columns ) );
		$gap     = self::length( $gap );
		$style   = sprintf( 'display:grid;grid-template-columns:repeat(%d,minmax(0,1fr));gap:%s', $columns, $gap );
		$classes = trim( 'cfmb-field-loop ' . $class );

		return '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '">' . $items . '</div>';
	}

	/**
	 * One item.
	 *
	 * @param string $html Item HTML, already escaped by the builder.
	 * @return string
	 */
	public static function item( string $html ): string {
		return '<div class="cfmb-field-loop__item">' . $html . '</div>';
	}

	/**
	 * The "no items" text, or a hint for editors in the builder.
	 *
	 * @param string $text    Text set on the module.
	 * @param bool   $editing Whether the builder is open.
	 * @return string
	 */
	public static function empty( string $text, bool $editing ): string {
		if ( '' !== $text ) {
			return '<p class="cfmb-field-loop__empty">' . esc_html( $text ) . '</p>';
		}

		return $editing ? self::notice( __( 'No items for this field on the post being previewed.', 'cfmb' ) ) : '';
	}

	/**
	 * A hint shown only in the builder.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function notice( string $text ): string {
		return '<p class="cfmb-field-loop__notice">' . esc_html( $text ) . '</p>';
	}

	/**
	 * A CSS length such as 20px or 1.5em, or 20px.
	 *
	 * @param string $gap Raw value.
	 * @return string
	 */
	public static function length( string $gap ): string {
		$gap = trim( $gap );

		return 1 === preg_match( '/^\d+(\.\d+)?(px|em|rem|%|vw)$/', $gap ) ? $gap : '20px';
	}
}
