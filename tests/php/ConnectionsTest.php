<?php
/**
 * Connections: storage, limits and the template API.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Connections\Connection_Repository;
use CFMB\Connections\Connection_Store;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || exit;

/**
 * Runs in wp-env with the plugin active.
 */
class ConnectionsTest extends WP_UnitTestCase {

	/**
	 * Make a books-to-authors connection.
	 *
	 * @param string $cardinality Cardinality.
	 * @return void
	 */
	private function define( string $cardinality = 'many_to_many' ): void {
		$repo  = cfmb()->get( Connection_Repository::class );
		$clean = $repo->schema()->sanitize(
			array(
				'title'    => 'Book authors',
				'name'     => 'book_authors',
				'active'   => true,
				'settings' => array(
					'from'            => 'post',
					'from_post_types' => array( 'post' ),
					'to'              => 'user',
					'cardinality'     => $cardinality,
				),
			),
			null,
			$repo->names_taken()
		);
		$repo->save( $clean );
	}

	/**
	 * Links work both ways and keep their order.
	 *
	 * @return void
	 */
	public function test_links_both_ways(): void {
		$this->define();
		$post  = self::factory()->post->create();
		$one   = self::factory()->user->create();
		$two   = self::factory()->user->create();

		$this->assertTrue( cfmb_connect( 'book_authors', $post, 'user_' . $two ) );
		$this->assertTrue( cfmb_connect( 'book_authors', $post, 'user_' . $one ) );

		$this->assertSame( array( $two, $one ), cfmb_get_connected( 'book_authors', $post, array( 'fields' => 'ids' ) ) );
		$this->assertSame( array( $post ), cfmb()->get( Connection_Store::class )->ids( 'book_authors', 'to', $one ) );
		$this->assertTrue( cfmb_is_connected( 'book_authors', $post, 'user_' . $one ) );

		cfmb_disconnect( 'book_authors', $post, 'user_' . $one );
		$this->assertFalse( cfmb_is_connected( 'book_authors', $post, 'user_' . $one ) );
	}

	/**
	 * In one-to-many, an author moves to the new book.
	 *
	 * @return void
	 */
	public function test_one_to_many_moves_links(): void {
		$this->define( 'one_to_many' );
		$first  = self::factory()->post->create();
		$second = self::factory()->post->create();
		$user   = self::factory()->user->create();

		cfmb_connect( 'book_authors', $first, 'user_' . $user );
		cfmb_connect( 'book_authors', $second, 'user_' . $user );

		$this->assertSame( array(), cfmb_get_connected( 'book_authors', $first, array( 'fields' => 'ids' ) ) );
		$this->assertSame( array( $user ), cfmb_get_connected( 'book_authors', $second, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Drafts don't show on the front end, and WP_Query can filter by a link.
	 *
	 * @return void
	 */
	public function test_visibility_and_query(): void {
		$this->define();
		$user  = self::factory()->user->create();
		$live  = self::factory()->post->create();
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		cfmb_connect( 'book_authors', $live, 'user_' . $user );
		cfmb_connect( 'book_authors', $draft, 'user_' . $user );

		$this->assertSame( array( $live ), cfmb_get_connected( 'book_authors', 'user_' . $user, array( 'fields' => 'ids' ) ) );

		$query = new \WP_Query(
			array(
				'cfmb_connected' => array(
					'connection' => 'book_authors',
					'object'     => 'user_' . $user,
				),
				'fields'         => 'ids',
			)
		);
		$this->assertSame( array( $live ), array_map( 'intval', $query->posts ) );
	}
}
