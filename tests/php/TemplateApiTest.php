<?php
/**
 * Template API and storage.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use WP_UnitTestCase;

/**
 * cfmb_get_field(), cfmb_update_field() and friends.
 */
class TemplateApiTest extends WP_UnitTestCase {

	/**
	 * Groups are cached per request; tests roll back the database between runs.
	 */
	public function set_up(): void {
		parent::set_up();
		cfmb()->get( Group_Repository::class )->flush();
	}

	/**
	 * Register a group with one field of each kind we test.
	 *
	 * @param array<int, array<int, array<string, string>>> $location Location rules.
	 * @return void
	 */
	private function register_group( array $location ): void {
		$clean = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Details',
				'active'   => true,
				'fields'   => array(
					array( 'type' => 'number', 'label' => 'Price', 'name' => 'price', 'settings' => array( 'min' => 0 ) ),
					array(
						'type'     => 'checkbox',
						'label'    => 'Colors',
						'name'     => 'colors',
						'settings' => array(
							'choices'       => "red : Red\nblue : Blue\ngreen : Green",
							'default_value' => "red",
						),
					),
					array( 'type' => 'toggle', 'label' => 'Featured', 'name' => 'featured' ),
				),
				'location' => $location,
			)
		);

		cfmb()->get( Group_Repository::class )->save( $clean );
	}

	public function test_post_values_round_trip(): void {
		$this->register_group( array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ) );
		$post = self::factory()->post->create();

		$this->assertSame( array( 'red' ), cfmb_get_field( 'colors', $post ), 'Default applies before a value is saved.' );

		$this->assertTrue( cfmb_update_field( 'price', '19.50', $post ) );
		$this->assertTrue( cfmb_update_field( 'colors', array( 'green', 'nope', 'blue' ), $post ) );
		$this->assertTrue( cfmb_update_field( 'featured', true, $post ) );

		$this->assertSame( 19.5, cfmb_get_field( 'price', $post ) );
		$this->assertSame( '19.5', cfmb_get_field( 'price', $post, false ) );
		$this->assertSame( array( 'green', 'blue' ), cfmb_get_field( 'colors', $post ) );
		$this->assertTrue( cfmb_get_field( 'featured', $post ) );
	}

	public function test_lists_are_one_meta_row_per_item(): void {
		$this->register_group( array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ) );
		$post = self::factory()->post->create();

		cfmb_update_field( 'colors', array( 'red', 'blue' ), $post );

		$this->assertSame( array( 'red', 'blue' ), get_post_meta( $post, 'colors', false ) );

		$query = new \WP_Query(
			array(
				'post_type'  => 'post',
				'fields'     => 'ids',
				'meta_query' => array( array( 'key' => 'colors', 'value' => 'blue' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$this->assertSame( array( $post ), $query->posts );
	}

	public function test_saved_empty_list_does_not_bring_back_default(): void {
		$this->register_group( array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ) );
		$post = self::factory()->post->create();

		cfmb_update_field( 'colors', array(), $post );

		$this->assertSame( array(), cfmb_get_field( 'colors', $post ) );
	}

	public function test_invalid_values_are_refused(): void {
		$this->register_group( array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ) );
		$post = self::factory()->post->create();

		$this->assertFalse( cfmb_update_field( 'price', '-5', $post ) );
		$this->assertFalse( cfmb_update_field( 'price', 'cheap', $post ) );
		$this->assertFalse( cfmb_update_field( 'not_a_field', 'x', $post ) );
	}

	public function test_terms_users_and_options(): void {
		$this->register_group(
			array(
				array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'category' ) ),
				array( array( 'param' => 'user_role', 'operator' => '==', 'value' => 'all' ) ),
				array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'options' ) ),
			)
		);

		$term = self::factory()->category->create();
		$user = self::factory()->user->create();

		cfmb_update_field( 'price', 3, "term_{$term}" );
		cfmb_update_field( 'price', 4, "user_{$user}" );
		cfmb_update_field( 'price', 5, 'option' );

		$this->assertSame( 3, cfmb_get_field( 'price', "term_{$term}" ) );
		$this->assertSame( 4, cfmb_get_field( 'price', "user_{$user}" ) );
		$this->assertSame( 5, cfmb_get_field( 'price', 'option' ) );
		$this->assertSame( '5', get_option( 'cfmb_options_price' ), 'Options page values use the cfmb_{page}_{field} name.' );
	}
}
