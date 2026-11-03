<?php
/**
 * Permission checks for an object's field values over REST.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Content\Options_Pages;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user may edit a post, term, user, comment, options page or widget,
 * the same checks the edit screens use.
 */
class Object_Access {

	/**
	 * Registered options pages.
	 *
	 * @var Options_Pages
	 */
	private Options_Pages $pages;

	/**
	 * Constructor.
	 *
	 * @param Options_Pages $pages Options pages.
	 */
	public function __construct( Options_Pages $pages ) {
		$this->pages = $pages;
	}

	/**
	 * Whether the current user can edit the object's fields.
	 *
	 * @param Object_Ref $ref Object.
	 * @return bool
	 */
	public function can_edit( Object_Ref $ref ): bool {
		switch ( $ref->kind() ) {
			case Object_Ref::POST:
				return $ref->id() > 0 && current_user_can( 'edit_post', $ref->id() );
			case Object_Ref::TERM:
				return $ref->id() > 0 && current_user_can( 'edit_term', $ref->id() );
			case Object_Ref::USER:
				return $ref->id() > 0 && current_user_can( 'edit_user', $ref->id() );
			case Object_Ref::COMMENT:
				return $ref->id() > 0 && current_user_can( 'edit_comment', $ref->id() );
			case Object_Ref::WIDGET:
				return '' !== $ref->page() && current_user_can( 'edit_theme_options' );
			case Object_Ref::OPTION:
				$page = $this->pages->get( $ref->page() );

				return $page && current_user_can( (string) $page['capability'] );
			default:
				return false;
		}
	}
}
