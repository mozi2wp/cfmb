<?php
/**
 * WordPress icons for the PHP admin screens.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The same icon set as the React screens (assets/src/shared/ui-icons.js),
 * printed as inline SVG. The markup comes from ui-icons.php, which
 * bin/build-ui-icons.js writes; nothing here takes user input.
 */
final class Ui_Icons {

	/**
	 * Icon markup by name.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $icons = null;

	/**
	 * An icon as escaped, inline SVG, or an empty string for an unknown name.
	 *
	 * @param string $name Icon name from bin/build-ui-icons.js.
	 * @param int    $size Width and height in pixels.
	 * @return string
	 */
	public static function svg( string $name, int $size = 24 ): string {
		if ( null === self::$icons ) {
			$loaded      = require __DIR__ . '/ui-icons.php';
			self::$icons = is_array( $loaded ) ? $loaded : array();
		}

		if ( ! isset( self::$icons[ $name ] ) ) {
			return '';
		}

		$svg = preg_replace(
			'/^<svg /',
			sprintf( '<svg width="%1$d" height="%1$d" aria-hidden="true" focusable="false" ', $size ),
			// wp_kses() drops "fill" from style attributes, so it becomes an attribute.
			str_replace( 'style="fill: none"', 'fill="none"', self::$icons[ $name ] )
		);

		return wp_kses( (string) $svg, self::allowed_html() );
	}

	/**
	 * Tags and attributes the icons use.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		$shape = array(
			'd'               => true,
			'fill'            => true,
			'fill-rule'       => true,
			'clip-rule'       => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'cx'              => true,
			'cy'              => true,
			'r'               => true,
			'rx'              => true,
			'ry'              => true,
			'x'               => true,
			'y'               => true,
			'x1'              => true,
			'x2'              => true,
			'y1'              => true,
			'y2'              => true,
			'width'           => true,
			'height'          => true,
			'points'          => true,
			'transform'       => true,
		);

		return array(
			'svg'     => array(
				'xmlns'        => true,
				'viewbox'      => true,
				'width'        => true,
				'height'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'aria-hidden'  => true,
				'focusable'    => true,
			),
			'path'    => $shape,
			'circle'  => $shape,
			'rect'    => $shape,
			'polygon' => $shape,
			'ellipse' => $shape,
			'line'    => $shape,
		);
	}
}
