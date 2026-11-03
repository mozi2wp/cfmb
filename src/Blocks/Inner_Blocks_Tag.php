<?php
/**
 * The <InnerBlocks /> tag in Field Block templates.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * A template marks where nested blocks go with one tag:
 *
 *     <InnerBlocks allowedBlocks='["core/heading","core/paragraph"]' templateLock="false" />
 *
 * On the front end the tag is replaced by the rendered inner blocks. In the
 * editor preview it becomes <cfmb-inner-blocks>, which the editor swaps for
 * the live inner blocks area. Supported attributes: allowedBlocks, template,
 * templateLock and orientation.
 */
final class Inner_Blocks_Tag {

	/**
	 * The tag, self-closing or with a closing tag.
	 */
	private const PATTERN = '#<InnerBlocks\b([^>]*?)/?>(?:\s*</InnerBlocks>)?#i';

	/**
	 * Attributes passed through to the editor.
	 */
	private const ATTRIBUTES = array( 'allowedblocks', 'template', 'templatelock', 'orientation' );

	/**
	 * Replace the first tag. Later ones are removed; a block has one inner blocks area.
	 *
	 * @param string $html    Template output.
	 * @param string $content Rendered inner blocks.
	 * @param bool   $preview Whether this is the editor preview.
	 * @return string
	 */
	public static function replace( string $html, string $content, bool $preview ): string {
		$done = false;

		return (string) preg_replace_callback(
			self::PATTERN,
			static function ( array $match ) use ( &$done, $content, $preview ): string {
				if ( $done ) {
					return '';
				}

				$done = true;

				return $preview ? '<cfmb-inner-blocks' . self::attributes( $match[1] ) . '></cfmb-inner-blocks>' : $content;
			},
			$html
		);
	}

	/**
	 * Known attributes of the tag, re-escaped.
	 *
	 * @param string $source Attribute part of the tag.
	 * @return string
	 */
	private static function attributes( string $source ): string {
		$out = '';

		preg_match_all( '#([a-zA-Z]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#', $source, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$name = strtolower( $match[1] );

			if ( in_array( $name, self::ATTRIBUTES, true ) ) {
				$value = isset( $match[3] ) && '' !== $match[3] ? $match[3] : $match[2];
				$out  .= sprintf( ' data-%s="%s"', $name, esc_attr( html_entity_decode( $value, ENT_QUOTES ) ) );
			}
		}

		return $out;
	}
}
