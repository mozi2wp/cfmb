<?php
/**
 * Finds which object and field a Dynamic Data source points at.
 *
 * @package CFMB
 */

namespace CFMB\Display;

use CFMB\Schema\Field;
use CFMB\Schema\Field_Locator;
use CFMB\Schema\Group;
use CFMB\Storage\Object_Ref;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Shared by the shortcode and Block Bindings. The `object` argument accepts:
 *
 * - empty, "post" or "current": the current post (the loop post inside a Query Loop)
 * - "term": the term of the archive being viewed
 * - "author": the author of the current post
 * - "user": the logged-in visitor (only their own values)
 * - "option" or "option:{page}": an options page
 * - "post_12", "12", "term_12", "user_3", "comment_5": one object by ID
 * - anything an integration adds through `cfmb/display/object`, such as "variation"
 */
class Resolver {

	/**
	 * Field lookup.
	 *
	 * @var Field_Locator
	 */
	private Field_Locator $locator;

	/**
	 * Visibility rules.
	 *
	 * @var Visibility
	 */
	private Visibility $visibility;

	/**
	 * Constructor.
	 *
	 * @param Field_Locator $locator    Field lookup.
	 * @param Visibility    $visibility Visibility rules.
	 */
	public function __construct( Field_Locator $locator, Visibility $visibility ) {
		$this->locator    = $locator;
		$this->visibility = $visibility;
	}

	/**
	 * Visibility rules.
	 *
	 * @return Visibility
	 */
	public function visibility(): Visibility {
		return $this->visibility;
	}

	/**
	 * Object reference for an `object` argument.
	 *
	 * @param string $object  Object argument.
	 * @param int    $post_id Current post, e.g. from block context; 0 to use the loop.
	 * @return array{ref: Object_Ref, via: string}|null
	 */
	public function object( string $object, int $post_id = 0 ): ?array {
		$object = trim( strtolower( $object ) );

		switch ( $object ) {
			case '':
			case 'post':
			case 'current':
				$id = $post_id ? $post_id : $this->current_post_id();

				return $id ? $this->entry( new Object_Ref( Object_Ref::POST, $id ) ) : null;

			case 'term':
				$term = get_queried_object();

				return $term instanceof WP_Term ? $this->entry( new Object_Ref( Object_Ref::TERM, (int) $term->term_id ) ) : null;

			case 'author':
				$id   = $post_id ? $post_id : $this->current_post_id();
				$post = $id ? get_post( $id ) : null;

				if ( ! $post instanceof WP_Post || ! $post->post_author || ! $this->visibility->can_read_post( (int) $post->ID ) ) {
					return null;
				}

				return $this->entry( new Object_Ref( Object_Ref::USER, (int) $post->post_author ), Visibility::VIA_AUTHOR );

			case 'user':
				$user = get_current_user_id();

				return $user ? $this->entry( new Object_Ref( Object_Ref::USER, $user ) ) : null;
		}

		$ref = 0 === strpos( $object, 'option' ) || 1 === preg_match( '/^(post|term|user|comment)_\d+$|^\d+$/', $object ) ? Object_Ref::from( $object ) : null;

		if ( $ref ) {
			return $this->entry( $ref );
		}

		/**
		 * Filters the object for an `object` argument CFMB does not know, such
		 * as "variation" (WooCommerce). Return { ref: Object_Ref, via: string } or null.
		 *
		 * @param array{ref: Object_Ref, via: string}|null $entry   Null by default.
		 * @param string                                   $object  Object argument, lowercase.
		 * @param int                                      $post_id Current post, or 0.
		 */
		$entry = apply_filters( 'cfmb/display/object', null, $object, $post_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return is_array( $entry ) && ( $entry['ref'] ?? null ) instanceof Object_Ref ? $this->entry( $entry['ref'], (string) ( $entry['via'] ?? '' ) ) : null;
	}

	/**
	 * Field, group and object for a source, if the visitor may see it.
	 *
	 * @param string $name    Field name.
	 * @param string $object  Object argument.
	 * @param int    $post_id Current post; 0 to use the loop.
	 * @param string $via     How the object was reached, when the caller knows better (see Visibility).
	 * @return array{field: Field, group: Group, ref: Object_Ref}|null
	 */
	public function find( string $name, string $object = '', int $post_id = 0, string $via = '' ): ?array {
		$name  = trim( $name );
		$entry = '' !== $name ? $this->object( $object, $post_id ) : null;

		if ( ! $entry || ! $this->visibility->can_read( $entry['ref'], '' !== $via ? $via : $entry['via'] ) ) {
			return null;
		}

		$found = $this->locator->find( $name, $entry['ref'] );

		if ( ! $found || ! $this->visibility->can_show_field( $found[0], $entry['ref'] ) ) {
			return null;
		}

		return array(
			'field' => $found[0],
			'group' => $found[1],
			'ref'   => $entry['ref'],
		);
	}

	/**
	 * Post in the loop, or the queried post.
	 *
	 * @return int
	 */
	private function current_post_id(): int {
		$id = get_the_ID();

		if ( ! $id ) {
			$queried = get_queried_object();
			$id      = $queried instanceof WP_Post ? $queried->ID : 0;
		}

		return (int) $id;
	}

	/**
	 * Result entry.
	 *
	 * @param Object_Ref $ref Object.
	 * @param string     $via How it was reached.
	 * @return array{ref: Object_Ref, via: string}
	 */
	private function entry( Object_Ref $ref, string $via = '' ): array {
		return array(
			'ref' => $ref,
			'via' => $via,
		);
	}
}
