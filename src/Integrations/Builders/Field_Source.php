<?php
/**
 * Finds the field a builder tag points at, inside or outside a Field Loop.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

use CFMB\Display\Resolver;
use CFMB\Display\Visibility;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Tags name a field by path:
 *
 * - "price": a field on the chosen object (see Resolver for `object`)
 * - "items.title": the Title sub-field of the Field Loop row looping "items"
 * - "items.links.url": the same one level down, inside a nested loop
 *
 * Paths with a dot only resolve inside a Field Loop over their parent; outside
 * one they are empty, as there is no row to read from.
 *
 * Inside a loop over posts, the current post is the loop post, so "price"
 * reads the price of each linked post. Inside a loop over connected terms
 * or users, the current object is that term or user.
 */
class Field_Source {

	/**
	 * Source lookup shared with the shortcode and Block Bindings.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Constructor.
	 *
	 * @param Resolver $resolver Source lookup.
	 */
	public function __construct( Resolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Source lookup.
	 *
	 * @return Resolver
	 */
	public function resolver(): Resolver {
		return $this->resolver;
	}

	/**
	 * Field, group and object for a path, if the visitor may see it.
	 *
	 * @param string $path    Field path.
	 * @param string $object  Object argument; ignored for sub-field paths.
	 * @param int    $post_id Post the builder is rendering; 0 to use the loop.
	 * @return array{field: Field, group: Group, ref: Object_Ref}|null
	 */
	public function find( string $path, string $object = '', int $post_id = 0 ): ?array {
		$path = trim( $path );

		if ( '' === $path || '@' === $path[0] ) {
			return null;
		}

		if ( false !== strpos( $path, '.' ) ) {
			return $this->find_in_row( $path );
		}

		$item = self::is_current( $object ) ? self::nearest_object_item() : null;

		if ( $item && in_array( $item['kind'], array( 'term', 'user' ), true ) ) {
			return $this->resolver->find( $path, $item['kind'] . '_' . (int) $item['id'], 0, Visibility::VIA_CONNECTION );
		}

		return $this->resolver->find( $path, $object, $this->post_for( $object, $post_id ) );
	}

	/**
	 * Object reference for an `object` argument, inside or outside a loop.
	 *
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering; 0 to use the loop.
	 * @return array{ref: Object_Ref, via: string}|null
	 */
	public function object( string $object, int $post_id = 0 ): ?array {
		$item = self::is_current( $object ) ? self::nearest_object_item() : null;

		if ( $item && in_array( $item['kind'], array( 'term', 'user' ), true ) ) {
			return array(
				'ref' => new Object_Ref( $item['kind'], (int) $item['id'] ),
				'via' => Visibility::VIA_CONNECTION,
			);
		}

		return $this->resolver->object( $object, $this->post_for( $object, $post_id ) );
	}

	/**
	 * Whether an `object` argument means "the current object".
	 *
	 * @param string $object Object argument.
	 * @return bool
	 */
	private static function is_current( string $object ): bool {
		return in_array( trim( strtolower( $object ) ), array( '', 'post', 'current' ), true );
	}

	/**
	 * Innermost open post, term or user item.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function nearest_object_item(): ?array {
		foreach ( array_reverse( Loop_Context::all() ) as $item ) {
			if ( in_array( $item['kind'], array( 'post', 'term', 'user' ), true ) ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Post that "current post" means: the loop post inside a Field Loop over
	 * posts, otherwise the post the builder passed.
	 *
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering.
	 * @return int
	 */
	public function post_for( string $object, int $post_id ): int {
		$item = self::nearest_object_item();
		$item = $item && 'post' === $item['kind'] ? $item : null;

		if ( $item && in_array( trim( strtolower( $object ) ), array( '', 'post', 'current', 'author' ), true ) ) {
			return (int) $item['id'];
		}

		return $post_id;
	}

	/**
	 * A sub-field of the nearest open row whose loop path is the path's parent.
	 *
	 * @param string $path Path with at least one dot.
	 * @return array{field: Field, group: Group, ref: Object_Ref}|null
	 */
	private function find_in_row( string $path ): ?array {
		$cut    = (int) strrpos( $path, '.' );
		$parent = substr( $path, 0, $cut );
		$name   = substr( $path, $cut + 1 );
		$items  = array_reverse( Loop_Context::all() );

		foreach ( $items as $item ) {
			if ( 'row' !== $item['kind'] || $item['path'] !== $parent ) {
				continue;
			}

			foreach ( $item['fields'] as $child ) {
				if ( $child instanceof Field && $child->base_name() === $name ) {
					return $this->resolver->visibility()->can_show_field( $child, $item['ref'] instanceof Object_Ref ? $item['ref'] : null )
						? array(
							'field' => $child,
							'group' => $item['group'],
							'ref'   => $item['ref'],
						)
						: null;
				}
			}

			return null;
		}

		return null;
	}
}
