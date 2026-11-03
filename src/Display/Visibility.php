<?php
/**
 * Who may see an object's field values through Dynamic Data.
 *
 * @package CFMB
 */

namespace CFMB\Display;

use CFMB\Schema\Field;
use CFMB\Storage\Object_Ref;
use WP_Comment;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Data (the shortcode and Block Bindings) only prints values the
 * current visitor could already see: published posts, or posts they may
 * read; no password-protected posts until the password is entered; user
 * values only for themselves, people who may edit them, the author of a
 * post being shown, or a user an editor linked through a Connection.
 * Password fields never show.
 */
class Visibility {

	/**
	 * Ways an object can be reached that change the answer.
	 */
	public const VIA_AUTHOR = 'author';

	/**
	 * Reached as a connected item, e.g. a user linked to a book as its author.
	 */
	public const VIA_CONNECTION = 'connection';

	/**
	 * Whether the current visitor may see values of an object.
	 *
	 * @param Object_Ref $ref Object.
	 * @param string     $via How the object was reached: "author" for the author of the current post, "connection" for a connected item.
	 * @return bool
	 */
	public function can_read( Object_Ref $ref, string $via = '' ): bool {
		$allowed = $this->decide( $ref, $via );

		/**
		 * Filters whether Dynamic Data may show an object's values to the current visitor.
		 *
		 * @param bool       $allowed Default decision.
		 * @param Object_Ref $ref     Object.
		 * @param string     $via     How the object was reached: "author", "connection" or empty.
		 */
		return (bool) apply_filters( 'cfmb/display/can_read', $allowed, $ref, $via ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Whether a field may show to the current visitor. Password fields never do.
	 *
	 * @param Field           $field Field.
	 * @param Object_Ref|null $ref   Object the value belongs to, when known.
	 * @return bool
	 */
	public function can_show_field( Field $field, ?Object_Ref $ref = null ): bool {
		if ( 'password' === $field->type() ) {
			return false;
		}

		/**
		 * Filters whether the current visitor may see a field's value.
		 *
		 * Runs for Dynamic Data, GraphQL and edit screens. Template functions
		 * such as cfmb_get_field() are not affected: theme code decides what it prints.
		 *
		 * @since 0.9.0
		 *
		 * @param bool            $allowed Default true.
		 * @param Field           $field   Field. Inside rows, the sub-field.
		 * @param Object_Ref|null $ref     Object, or null when not known yet.
		 * @param string          $context display or editor.
		 */
		return (bool) apply_filters( 'cfmb/field/can_view', true, $field, $ref, 'display' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Whether a post linked from a field may show: the same test as the post itself.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function can_read_post( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return true;
		}

		if ( post_password_required( $post ) ) {
			return false;
		}

		if ( 'attachment' === $post->post_type ) {
			$parent = (int) $post->post_parent;

			return 0 === $parent || $this->can_read_post( $parent );
		}

		return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );
	}

	/**
	 * The decision before filtering.
	 *
	 * @param Object_Ref $ref Object.
	 * @param string     $via How the object was reached.
	 * @return bool
	 */
	private function decide( Object_Ref $ref, string $via ): bool {
		switch ( $ref->kind() ) {
			case Object_Ref::POST:
				return $this->can_read_post( $ref->id() );

			case Object_Ref::TERM:
				$term = get_term( $ref->id() );

				return $term instanceof WP_Term && ( is_taxonomy_viewable( $term->taxonomy ) || current_user_can( 'edit_term', $term->term_id ) );

			case Object_Ref::USER:
				if ( ! get_userdata( $ref->id() ) ) {
					return false;
				}

				return in_array( $via, array( self::VIA_AUTHOR, self::VIA_CONNECTION ), true ) || get_current_user_id() === $ref->id() || current_user_can( 'edit_user', $ref->id() );

			case Object_Ref::COMMENT:
				$comment = get_comment( $ref->id() );

				if ( ! $comment instanceof WP_Comment ) {
					return false;
				}

				if ( current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
					return true;
				}

				return '1' === (string) $comment->comment_approved && $this->can_read_post( (int) $comment->comment_post_ID );

			case Object_Ref::OPTION:
			case Object_Ref::WIDGET:
			case Object_Ref::BLOCK:
				// Site-wide settings and block content are shown by design.
				return true;

			default:
				return false;
		}
	}
}
