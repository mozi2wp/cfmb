<?php
/**
 * Post type, taxonomy and options page definitions.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Content\Options_Page_Schema;
use CFMB\Content\Post_Type_Schema;
use CFMB\Content\Taxonomy_Schema;
use WP_UnitTestCase;

/**
 * Sanitizing definitions and turning them into WordPress arguments.
 */
class ContentSchemaTest extends WP_UnitTestCase {

	public function test_post_type_is_sanitized_and_labels_are_built(): void {
		$schema = new Post_Type_Schema();
		$item   = $schema->sanitize(
			array(
				'title'    => 'Books',
				'name'     => 'Book',
				'active'   => true,
				'settings' => array(
					'singular' => 'Book',
					'supports' => array( 'title', 'nonsense' ),
				),
			)
		);

		$this->assertIsArray( $item );
		$this->assertSame( 'book', $item['name'] );
		$this->assertStringStartsWith( 'cpt_', $item['key'] );
		$this->assertSame( array( 'title' ), $item['settings']['supports'] );

		$args = $schema->build_args( $item );
		$this->assertSame( 'Add New Book', $args['labels']['add_new_item'] );
		$this->assertSame( 'No books found.', $args['labels']['not_found'] );
		$this->assertSame( 'book', $args['rewrite']['slug'] );
	}

	public function test_reserved_and_taken_names_are_refused(): void {
		$schema = new Post_Type_Schema();

		$this->assertWPError( $schema->sanitize( array( 'title' => 'Pages', 'name' => 'page' ) ) );
		$this->assertWPError( $schema->sanitize( array( 'title' => 'Long', 'name' => str_repeat( 'a', 21 ) ) ) );
		$this->assertWPError( $schema->sanitize( array( 'title' => 'Books', 'name' => 'book' ), null, array( 'book' ) ) );
	}

	public function test_taxonomy_keeps_post_types_registered_later(): void {
		$schema = new Taxonomy_Schema();
		$item   = $schema->sanitize(
			array(
				'title'    => 'Genres',
				'name'     => 'genre',
				'settings' => array( 'post_types' => array( 'book', 'not_yet' ) ),
			)
		);

		$this->assertSame( array( 'book', 'not_yet' ), $item['settings']['post_types'] );
		$this->assertWPError( $schema->sanitize( array( 'title' => 'Years', 'name' => 'year' ) ) );
	}

	public function test_options_page_args(): void {
		$schema = new Options_Page_Schema();
		$item   = $schema->sanitize(
			array(
				'title'    => 'Site settings',
				'name'     => 'site',
				'settings' => array( 'parent' => 'options-general.php' ),
			)
		);
		$args   = $schema->build_args( $item );

		$this->assertSame( 'site', $args['id'] );
		$this->assertSame( 'options-general.php', $args['parent_slug'] );
		$this->assertSame( 'manage_options', $args['capability'] );
	}
}
