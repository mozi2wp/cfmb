<?php
/**
 * Reference to the object a value belongs to.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * A post, term, user, comment, options page, widget or Field Block instance.
 *
 * Accepted forms in the template API: null (current post), a post ID,
 * "post_12", "term_12", "user_3", "comment_5", "option" (default options
 * page), "option:{page}", "widget:{widget id}" such as "widget:text-3",
 * or a WP_Post, WP_Term, WP_User or WP_Comment object.
 *
 * Attachments and menu items are posts; menus are terms.
 *
 * Inside a Field Block template, null means the block being rendered, so
 * cfmb_get_field( 'title' ) reads the block's own values. Pass get_the_ID()
 * to read the surrounding post there.
 */
final class Object_Ref {

	public const POST   = 'post';
	public const TERM   = 'term';
	public const USER   = 'user';
	public const OPTION  = 'option';
	public const COMMENT = 'comment';
	public const WIDGET  = 'widget';
	public const BLOCK   = 'block';

	public const DEFAULT_OPTIONS_PAGE = 'options';

	/**
	 * Object kind.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * Object ID; 0 for options.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Options page ID, widget ID or block instance ID; empty for other kinds.
	 *
	 * @var string
	 */
	private string $page;

	/**
	 * Constructor.
	 *
	 * @param string $kind One of the class constants.
	 * @param int    $id   Object ID.
	 * @param string $page Options page ID, widget ID or block instance ID.
	 */
	public function __construct( string $kind, int $id = 0, string $page = '' ) {
		$this->kind = $kind;
		$this->id   = $id;
		$this->page = $page;
	}

	/**
	 * Parse any accepted reference form.
	 *
	 * @param mixed $ref Reference.
	 * @return Object_Ref|null Null when the reference can't be resolved.
	 */
	public static function from( $ref ): ?Object_Ref {
		if ( $ref instanceof self ) {
			return $ref;
		}

		if ( null === $ref || false === $ref || '' === $ref ) {
			$block = Block_Context::current();

			return $block ? $block : self::current_post();
		}

		if ( $ref instanceof WP_Post ) {
			return new self( self::POST, (int) $ref->ID );
		}

		if ( $ref instanceof WP_Term ) {
			return new self( self::TERM, (int) $ref->term_id );
		}

		if ( $ref instanceof WP_User ) {
			return new self( self::USER, (int) $ref->ID );
		}

		if ( $ref instanceof WP_Comment ) {
			return new self( self::COMMENT, (int) $ref->comment_ID );
		}

		if ( is_int( $ref ) || ( is_string( $ref ) && ctype_digit( $ref ) ) ) {
			return (int) $ref > 0 ? new self( self::POST, (int) $ref ) : null;
		}

		if ( ! is_string( $ref ) ) {
			return null;
		}

		if ( in_array( $ref, array( 'option', 'options' ), true ) ) {
			return new self( self::OPTION, 0, self::DEFAULT_OPTIONS_PAGE );
		}

		if ( 0 === strpos( $ref, 'option:' ) ) {
			$page = sanitize_key( substr( $ref, 7 ) );

			return '' === $page ? null : new self( self::OPTION, 0, $page );
		}

		if ( 0 === strpos( $ref, 'widget:' ) ) {
			$widget = self::sanitize_widget_id( substr( $ref, 7 ) );

			return '' === $widget ? null : new self( self::WIDGET, 0, $widget );
		}

		if ( preg_match( '/^(post|term|user|comment)_(\d+)$/', $ref, $parts ) ) {
			return new self( $parts[1], (int) $parts[2] );
		}

		return null;
	}

	/**
	 * Widget ID such as "text-3": lowercase letters, digits, dashes and underscores.
	 *
	 * @param string $id Raw widget ID.
	 * @return string
	 */
	public static function sanitize_widget_id( string $id ): string {
		$id = strtolower( $id );

		return 1 === preg_match( '/^[a-z0-9_-]{1,100}$/', $id ) ? $id : '';
	}

	/**
	 * Post in the loop, or the queried post.
	 *
	 * @return Object_Ref|null
	 */
	private static function current_post(): ?Object_Ref {
		$id = get_the_ID();

		if ( ! $id ) {
			$queried = get_queried_object();
			$id      = $queried instanceof WP_Post ? $queried->ID : 0;
		}

		return $id ? new self( self::POST, (int) $id ) : null;
	}

	/**
	 * Object kind.
	 *
	 * @return string
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * Object ID.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Options page ID, or the widget ID for widgets.
	 *
	 * @return string
	 */
	public function page(): string {
		return $this->page;
	}

	/**
	 * Whether values live in the options table: options pages and widgets.
	 *
	 * @return bool
	 */
	public function uses_options(): bool {
		return self::OPTION === $this->kind || self::WIDGET === $this->kind;
	}

	/**
	 * Whether this is a Field Block instance, whose values live in block attributes.
	 *
	 * @return bool
	 */
	public function is_block(): bool {
		return self::BLOCK === $this->kind;
	}

	/**
	 * Meta type for the metadata API, or empty for options, widgets and blocks.
	 *
	 * @return string
	 */
	public function meta_type(): string {
		return $this->uses_options() || $this->is_block() ? '' : $this->kind;
	}

	/**
	 * String form, e.g. "term_12", "option:options", "widget:text-3" or "block:b1a2".
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->uses_options() || $this->is_block() ? $this->kind . ':' . $this->page : $this->kind . '_' . $this->id;
	}
}
