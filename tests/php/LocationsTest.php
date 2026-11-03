<?php
/**
 * Comment, attachment, menu, menu item and widget locations.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Location\Context;
use CFMB\Location\Rule_Registry;
use CFMB\Storage\Object_Ref;
use WP_UnitTestCase;

/**
 * New contexts and rules.
 */
class LocationsTest extends WP_UnitTestCase {

	public function test_attachments_get_their_own_context(): void {
		$id      = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$context = Context::for_post( $id, 'attachment' );
		$rules   = cfmb()->get( Rule_Registry::class );

		$this->assertSame( Context::ATTACHMENT, $context->kind() );
		$this->assertTrue( $rules->get( 'attachment' )->match( $context, '==', 'image' ) );
		$this->assertFalse( $rules->get( 'post_type' )->match( $context, '==', 'all' ) );
	}

	public function test_comment_rule_checks_the_post_type(): void {
		$post    = self::factory()->post->create();
		$comment = self::factory()->comment->create( array( 'comment_post_ID' => $post ) );
		$rules   = cfmb()->get( Rule_Registry::class );

		$this->assertTrue( $rules->get( 'comment' )->match( Context::for_comment( $comment ), '==', 'post' ) );
		$this->assertFalse( $rules->get( 'comment' )->match( Context::for_comment( $comment ), '==', 'page' ) );
	}

	public function test_menu_items_follow_their_menu(): void {
		$menu = wp_create_nav_menu( 'Main' );
		$item = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Home', 'menu-item-url' => 'https://example.org', 'menu-item-status' => 'publish' ) );

		$context = Context::for_post( $item, 'nav_menu_item' );
		$rules   = cfmb()->get( Rule_Registry::class );

		$this->assertSame( Context::MENU_ITEM, $context->kind() );
		$this->assertTrue( $rules->get( 'nav_menu_item' )->match( $context, '==', 'menu/main' ) );
		$this->assertTrue( $rules->get( 'nav_menu' )->match( Context::for_term( 'nav_menu', $menu ), '==', 'menu/main' ) );
	}

	public function test_widget_refs(): void {
		$ref = Object_Ref::from( 'widget:text-3' );

		$this->assertSame( 'widget:text-3', (string) $ref );
		$this->assertTrue( $ref->uses_options() );
		$this->assertSame( 'text', Context::from_ref( $ref )->get( 'id_base' ) );
		$this->assertNull( Object_Ref::from( 'widget:' ) );
	}
}
