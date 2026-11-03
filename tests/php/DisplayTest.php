<?php
/**
 * Dynamic Data: shortcode, bindings and visibility.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Display\Resolver;
use CFMB\Integrations\Bindings\Editor_Values;
use CFMB\Integrations\Bindings\Source;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use WP_UnitTestCase;

/**
 * What visitors see through [cfmb] and Block Bindings.
 */
class DisplayTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		cfmb()->get( Group_Repository::class )->flush();

		$clean = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Book',
				'active'   => true,
				'fields'   => array(
					array( 'type' => 'text', 'label' => 'Price', 'name' => 'price' ),
					array( 'type' => 'password', 'label' => 'Secret', 'name' => 'secret' ),
					array(
						'type'     => 'checkbox',
						'label'    => 'Colors',
						'name'     => 'colors',
						'settings' => array( 'choices' => "red : Red\nblue : Blue" ),
					),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			)
		);
		cfmb()->get( Group_Repository::class )->save( $clean );
		cfmb()->get( Group_Repository::class )->flush();
	}

	public function test_shortcode_prints_escaped_value(): void {
		$post = self::factory()->post->create();
		cfmb_update_field( 'price', '<em>12</em>', $post );
		cfmb_update_field( 'colors', array( 'red', 'blue' ), $post );

		$this->assertSame( '12', do_shortcode( '[cfmb field="price" object="' . $post . '"]' ) );
		$this->assertSame( 'Red / Blue', do_shortcode( '[cfmb field="colors" object="' . $post . '" separator=" / "]' ) );
		$this->assertSame( 'none', do_shortcode( '[cfmb field="missing" object="' . $post . '" default="none"]' ) );
	}

	public function test_drafts_password_posts_and_password_fields_stay_hidden(): void {
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$protected = self::factory()->post->create( array( 'post_password' => 'pw' ) );
		$public    = self::factory()->post->create();

		foreach ( array( $draft, $protected, $public ) as $post ) {
			update_post_meta( $post, 'price', '5' );
			update_post_meta( $post, 'secret', 'hunter2' );
		}

		wp_set_current_user( 0 );

		$this->assertSame( '', do_shortcode( '[cfmb field="price" object="' . $draft . '"]' ) );
		$this->assertSame( '', do_shortcode( '[cfmb field="price" object="' . $protected . '"]' ) );
		$this->assertSame( '5', do_shortcode( '[cfmb field="price" object="' . $public . '"]' ) );
		$this->assertSame( '', do_shortcode( '[cfmb field="secret" object="' . $public . '"]' ) );
	}

	public function test_user_values_only_for_self_or_author(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = self::factory()->post->create( array( 'post_author' => $author ) );

		wp_set_current_user( 0 );
		$resolver = cfmb()->get( Resolver::class );

		$this->assertNull( $resolver->object( 'user' ) );
		$this->assertSame( 'author', $resolver->object( 'author', $post )['via'] );
	}

	public function test_binding_parts_by_attribute(): void {
		$parts = array(
			'html'  => '<a href="x">T</a>',
			'text'  => 'T <b>',
			'url'   => 'javascript:alert(1)',
			'alt'   => 'Alt',
			'title' => 'Title',
			'id'    => 7,
		);

		$this->assertSame( '', Source::pick( $parts, 'url' ) );
		$this->assertSame( 'T &lt;b&gt;', Source::pick( $parts, 'text' ) );
		$this->assertSame( '<a href="x">T</a>', Source::pick( $parts, 'content' ) );
		$this->assertSame( 7, Source::pick( $parts, 'id' ) );
	}

	public function test_editor_values_save_through_field_validation(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post   = self::factory()->post->create( array( 'post_author' => $editor ) );
		wp_set_current_user( $editor );

		$values = cfmb()->get( Editor_Values::class );

		$this->assertArrayHasKey( 'price', $values->editable_fields( $post ) );
		$this->assertArrayNotHasKey( 'colors', $values->editable_fields( $post ) );
		$this->assertTrue( $values->update( array( 'price' => '20', 'colors' => 'red' ), get_post( $post ) ) );
		$this->assertSame( '20', get_post_meta( $post, 'price', true ) );
		$this->assertSame( array(), get_post_meta( $post, 'colors' ) );
	}
}
