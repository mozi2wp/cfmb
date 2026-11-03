<?php
/**
 * Describes the screen or object fields are shown for.
 *
 * @package CFMB
 */

namespace CFMB\Location;

use CFMB\Core\Plugin;
use CFMB\Storage\Object_Ref;
use CFMB\Storage\Store_Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * What location rules are checked against: a post, a term, a user, an
 * options page, a comment, an attachment, a menu, a menu item, a widget or a
 * Field Block.
 * Rules read details (template, terms, roles) lazily from the object itself,
 * so building a context is cheap.
 *
 * Attachments and menu items are posts, and menus are terms, but each has
 * its own kind here so post and taxonomy rules don't put fields on them.
 */
final class Context {

	public const ATTACHMENT = 'attachment';
	public const MENU       = 'menu';
	public const MENU_ITEM  = 'menu_item';
	public const COMMENT    = Object_Ref::COMMENT;
	public const WIDGET     = Object_Ref::WIDGET;
	public const BLOCK      = Object_Ref::BLOCK;

	/**
	 * Object kind, one of the Object_Ref constants.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * Details such as post_type, post_id, taxonomy, user_form, page.
	 *
	 * @var array<string, mixed>
	 */
	private array $details;

	/**
	 * Constructor.
	 *
	 * @param string               $kind    Object kind.
	 * @param array<string, mixed> $details Details.
	 */
	public function __construct( string $kind, array $details = array() ) {
		$this->kind    = $kind;
		$this->details = $details;
	}

	/**
	 * Context for a post edit screen.
	 *
	 * @param int    $post_id   Post ID; 0 while the post does not exist yet.
	 * @param string $post_type Post type.
	 * @return Context
	 */
	public static function for_post( int $post_id, string $post_type ): Context {
		if ( 'attachment' === $post_type ) {
			return self::for_attachment( $post_id );
		}

		if ( 'nav_menu_item' === $post_type ) {
			return self::for_menu_item( $post_id );
		}

		return new self(
			Object_Ref::POST,
			array(
				'post_id'   => $post_id,
				'post_type' => $post_type,
			)
		);
	}

	/**
	 * Context for a term add or edit screen.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term_id  Term ID; 0 on the add form.
	 * @return Context
	 */
	public static function for_term( string $taxonomy, int $term_id = 0 ): Context {
		if ( 'nav_menu' === $taxonomy ) {
			return self::for_menu( $term_id );
		}

		return new self(
			Object_Ref::TERM,
			array(
				'taxonomy' => $taxonomy,
				'term_id'  => $term_id,
			)
		);
	}

	/**
	 * Context for a user screen.
	 *
	 * @param int    $user_id User ID; 0 on the add form.
	 * @param string $form    "add" or "edit".
	 * @return Context
	 */
	public static function for_user( int $user_id, string $form ): Context {
		return new self(
			Object_Ref::USER,
			array(
				'user_id'   => $user_id,
				'user_form' => $form,
			)
		);
	}

	/**
	 * Context for an options page.
	 *
	 * @param string $page Options page ID.
	 * @return Context
	 */
	public static function for_options_page( string $page ): Context {
		return new self( Object_Ref::OPTION, array( 'page' => $page ) );
	}

	/**
	 * Context for an attachment's edit screen.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return Context
	 */
	public static function for_attachment( int $attachment_id ): Context {
		return new self(
			self::ATTACHMENT,
			array(
				'post_id'   => $attachment_id,
				'post_type' => 'attachment',
			)
		);
	}

	/**
	 * Context for a navigation menu.
	 *
	 * @param int $menu_id Menu term ID; 0 while the menu is being created.
	 * @return Context
	 */
	public static function for_menu( int $menu_id ): Context {
		return new self( self::MENU, array( 'menu_id' => $menu_id ) );
	}

	/**
	 * Context for one item of a navigation menu.
	 *
	 * @param int $item_id Menu item post ID.
	 * @param int $menu_id Menu term ID; looked up from the item when 0.
	 * @param int $depth   Nesting depth, 0 for top-level items; -1 when unknown.
	 * @return Context
	 */
	public static function for_menu_item( int $item_id, int $menu_id = 0, int $depth = -1 ): Context {
		if ( ! $menu_id && $item_id ) {
			$menus   = wp_get_object_terms( $item_id, 'nav_menu', array( 'fields' => 'ids' ) );
			$menu_id = is_array( $menus ) && $menus ? (int) $menus[0] : 0;
		}

		return new self(
			self::MENU_ITEM,
			array(
				'post_id' => $item_id,
				'menu_id' => $menu_id,
				'depth'   => $depth,
			)
		);
	}

	/**
	 * Context for a comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return Context
	 */
	public static function for_comment( int $comment_id ): Context {
		$comment = $comment_id ? get_comment( $comment_id ) : null;
		$post_id = $comment instanceof \WP_Comment ? (int) $comment->comment_post_ID : 0;

		return new self(
			self::COMMENT,
			array(
				'comment_id' => $comment_id,
				'post_id'    => $post_id,
				'post_type'  => $post_id ? (string) get_post_type( $post_id ) : '',
			)
		);
	}

	/**
	 * Context for a classic widget.
	 *
	 * @param string $id_base   Widget type, e.g. "text".
	 * @param string $widget_id Widget instance ID, e.g. "text-3"; empty for the widget template.
	 * @return Context
	 */
	public static function for_widget( string $id_base, string $widget_id = '' ): Context {
		return new self(
			self::WIDGET,
			array(
				'id_base'   => $id_base,
				'widget_id' => $widget_id,
			)
		);
	}

	/**
	 * Context for a Field Block.
	 *
	 * @param string $block Block name, e.g. cfmb/hero.
	 * @return Context
	 */
	public static function for_block( string $block ): Context {
		return new self( self::BLOCK, array( 'block' => $block ) );
	}

	/**
	 * Context for an object reference, used by the template API.
	 *
	 * @param Object_Ref $ref Object.
	 * @return Context
	 */
	public static function from_ref( Object_Ref $ref ): Context {
		switch ( $ref->kind() ) {
			case Object_Ref::TERM:
				$term = get_term( $ref->id() );

				return self::for_term( $term instanceof \WP_Term ? $term->taxonomy : '', $ref->id() );

			case Object_Ref::USER:
				return self::for_user( $ref->id(), 'edit' );

			case Object_Ref::OPTION:
				return self::for_options_page( $ref->page() );

			case Object_Ref::COMMENT:
				return self::for_comment( $ref->id() );

			case Object_Ref::WIDGET:
				return self::for_widget( (string) preg_replace( '/-\d+$/', '', $ref->page() ), $ref->page() );

			case Object_Ref::BLOCK:
				return self::for_block( Plugin::instance()->get( Store_Resolver::class )->blocks()->block_name( $ref ) );

			default:
				return self::for_post( $ref->id(), (string) get_post_type( $ref->id() ) );
		}
	}

	/**
	 * Copy with extra details, such as a template or terms picked on the
	 * edit screen but not saved yet.
	 *
	 * @param array<string, mixed> $details Details to add or replace.
	 * @return Context
	 */
	public function with( array $details ): Context {
		return new self( $this->kind, array_merge( $this->details, $details ) );
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
	 * Read a detail.
	 *
	 * @param string $name     Detail name.
	 * @param mixed  $fallback Value when missing.
	 * @return mixed
	 */
	public function get( string $name, $fallback = null ) {
		return $this->details[ $name ] ?? $fallback;
	}
}
