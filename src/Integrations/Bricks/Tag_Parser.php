<?php
/**
 * Reads CFMB tag strings such as `cfmb_items.title:plain`.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Bricks;

defined( 'ABSPATH' ) || exit;

/**
 * Tag format: `{prefix}{path}[:arg[:arg…]]`. The path is a field path
 * (`items.title`) or, for query types, `@connection/{id}`.
 *
 * Arguments are flags (`plain`, `url`) or `key=value` pairs (`size=medium`,
 * `object=term_5`). `date=…` takes the rest of the tag, so a date format may
 * contain colons. In `object=`, a slash stands for the colon of
 * `option:{page}` (`object=option/footer`).
 */
final class Tag_Parser {

	/**
	 * Parse a tag.
	 *
	 * @param string                $tag       Tag without braces.
	 * @param string                $prefix    Tag prefix, e.g. cfmb_.
	 * @param array<string, string> $item_tags Loop item tag suffixes => @ paths.
	 * @return array{path: string, args: array<string, string>}|null Null when not a CFMB tag.
	 */
	public static function parse( string $tag, string $prefix, array $item_tags ): ?array {
		$tag = trim( $tag );

		if ( 0 !== strpos( $tag, $prefix ) ) {
			return null;
		}

		$parts = explode( ':', substr( $tag, strlen( $prefix ) ) );
		$path  = strtolower( trim( (string) array_shift( $parts ) ) );

		if ( isset( $item_tags[ $path ] ) ) {
			$path = $item_tags[ $path ];
		} elseif ( 1 !== preg_match( '/^([a-z0-9_]+(\.[a-z0-9_]+)*|@connection\/[a-z0-9_-]+)$/', $path ) ) {
			return null;
		}

		$args = array();

		while ( $parts ) {
			$part = (string) array_shift( $parts );

			if ( 0 === strpos( $part, 'date=' ) ) {
				$args['date'] = substr( implode( ':', array_merge( array( $part ), $parts ) ), 5 );
				break;
			}

			$pair  = explode( '=', $part, 2 );
			$key   = sanitize_key( $pair[0] );
			$value = isset( $pair[1] ) ? trim( $pair[1] ) : '1';

			if ( '' === $key ) {
				continue;
			}

			$args[ $key ] = 'object' === $key ? str_replace( '/', ':', strtolower( $value ) ) : $value;
		}

		return array(
			'path' => $path,
			'args' => $args,
		);
	}
}
