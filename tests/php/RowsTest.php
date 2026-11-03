<?php
/**
 * Group, Repeater and Stacks storage and the row template API.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_UnitTestCase;

/**
 * Flattened storage, moves, paged saves and cfmb_have_rows().
 */
class RowsTest extends WP_UnitTestCase {

	/**
	 * Post the values are saved on.
	 *
	 * @var int
	 */
	private int $post_id;

	public function set_up(): void {
		parent::set_up();

		$repository = cfmb()->get( Group_Repository::class );
		$repository->flush();

		$clean = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Page builder',
				'active'   => true,
				'fields'   => array(
					array(
						'type'   => 'repeater',
						'label'  => 'Items',
						'name'   => 'items',
						'fields' => array(
							array( 'type' => 'text', 'label' => 'Title', 'name' => 'title' ),
							array( 'type' => 'link', 'label' => 'Link', 'name' => 'link' ),
						),
					),
					array(
						'type'    => 'stacks',
						'label'   => 'Sections',
						'name'    => 'sections',
						'layouts' => array(
							array(
								'label'  => 'Hero',
								'name'   => 'hero',
								'fields' => array( array( 'type' => 'text', 'label' => 'Heading', 'name' => 'heading' ) ),
							),
							array(
								'label'  => 'List',
								'name'   => 'list',
								'fields' => array(
									array(
										'type'   => 'repeater',
										'label'  => 'Entries',
										'name'   => 'entries',
										'fields' => array( array( 'type' => 'text', 'label' => 'Label', 'name' => 'label' ) ),
									),
								),
							),
						),
					),
					array(
						'type'   => 'group',
						'label'  => 'Hero',
						'name'   => 'hero',
						'fields' => array( array( 'type' => 'text', 'label' => 'Title', 'name' => 'title' ) ),
					),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			)
		);

		$this->assertIsArray( $clean );
		$repository->save( $clean );
		$this->post_id = self::factory()->post->create();
	}

	public function test_repeater_values_are_flattened(): void {
		$this->assertTrue(
			cfmb_update_field(
				'items',
				array(
					array( 'title' => 'One', 'link' => array( 'url' => 'https://example.com' ) ),
					array( 'title' => 'Two' ),
				),
				$this->post_id
			)
		);

		$this->assertSame( '2', get_post_meta( $this->post_id, 'items', true ) );
		$this->assertSame( 'Two', get_post_meta( $this->post_id, 'items_1_title', true ) );
		$this->assertSame( 'https://example.com', get_post_meta( $this->post_id, 'items_0_link_url', true ) );
		$this->assertSame( 'Two', cfmb_get_field( 'items', $this->post_id )[1]['title'] );
	}

	public function test_shrinking_removes_tail_rows(): void {
		cfmb_update_field( 'items', array( array( 'title' => 'A' ), array( 'title' => 'B' ), array( 'title' => 'C' ) ), $this->post_id );
		cfmb_update_field( 'items', array( array( 'title' => 'A' ) ), $this->post_id );

		$this->assertSame( '1', get_post_meta( $this->post_id, 'items', true ) );
		$this->assertFalse( metadata_exists( 'post', $this->post_id, 'items_2_title' ) );
		$this->assertFalse( metadata_exists( 'post', $this->post_id, 'items_1_link_url' ) );
	}

	public function test_paged_save_moves_rows_it_never_loaded(): void {
		cfmb_update_field( 'items', array( array( 'title' => 'A' ), array( 'title' => 'B' ), array( 'title' => 'C' ) ), $this->post_id );

		// Row 0 removed in the editor; rows 1 and 2 were never loaded.
		cfmb_update_field(
			'items',
			array(
				'paged' => 1,
				'rows'  => array(
					array(
						'_keep' => 1,
						'_from' => 1,
					),
					array(
						'_keep' => 1,
						'_from' => 2,
					),
				),
			),
			$this->post_id
		);

		$this->assertSame( array( 'B', 'C' ), wp_list_pluck( cfmb_get_field( 'items', $this->post_id ), 'title' ) );
		$this->assertFalse( metadata_exists( 'post', $this->post_id, 'items_2_title' ) );
	}

	public function test_stacks_store_layout_names_one_row_each(): void {
		cfmb_update_field(
			'sections',
			array(
				array( '_layout' => 'hero', 'heading' => 'Hi' ),
				array( '_layout' => 'list', 'entries' => array( array( 'label' => 'x' ) ) ),
			),
			$this->post_id
		);

		$this->assertSame( array( 'hero', 'list' ), get_post_meta( $this->post_id, 'sections' ) );
		$this->assertSame( 'x', get_post_meta( $this->post_id, 'sections_1_entries_0_label', true ) );

		$found = get_posts(
			array(
				'meta_key'   => 'sections', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'list', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ids',
			)
		);
		$this->assertSame( array( $this->post_id ), $found );

		// Swapping layouts clears values the new layout won't overwrite.
		cfmb_update_field( 'sections', array( array( '_layout' => 'list', 'entries' => array() ) ), $this->post_id );
		$this->assertFalse( metadata_exists( 'post', $this->post_id, 'sections_0_heading' ) );
		$this->assertFalse( metadata_exists( 'post', $this->post_id, 'sections_1_entries' ) );
	}

	public function test_row_loop(): void {
		cfmb_update_field(
			'sections',
			array(
				array( '_layout' => 'hero', 'heading' => 'Hi' ),
				array( '_layout' => 'list', 'entries' => array( array( 'label' => 'a' ), array( 'label' => 'b' ) ) ),
			),
			$this->post_id
		);
		cfmb_update_field( 'hero', array( 'title' => 'Top' ), $this->post_id );

		$seen = array();
		while ( cfmb_have_rows( 'sections', $this->post_id ) ) {
			cfmb_the_row();
			$seen[] = cfmb_get_row_layout();

			while ( cfmb_have_rows( 'entries' ) ) {
				cfmb_the_row();
				$seen[] = cfmb_get_sub_field( 'label' );
			}
		}

		$this->assertSame( array( 'hero', 'list', 'a', 'b' ), $seen );

		$this->assertTrue( cfmb_have_rows( 'hero', $this->post_id ) );
		cfmb_the_row();
		$this->assertSame( 'Top', cfmb_get_sub_field( 'title' ) );
		cfmb_reset_rows();
	}

	public function test_errors_point_at_the_stored_row(): void {
		$field  = cfmb()->get( Group_Repository::class )->active()[0]->fields()[0];
		$values = cfmb()->get( Field_Values::class );
		$link   = $field->sub_fields()[1];

		// A row loaded from position 3 keeps that position in the error path.
		$clean  = $values->sanitize( $field, array( array( '_from' => 3, 'link' => array( 'title' => 'No address' ) ) ) );
		$errors = $values->validate_tree( $field, $clean, true );
		$this->assertArrayHasKey( '3.' . $link->key(), $errors );

		// A new row isn't stored anywhere, so its problem goes on the field.
		$clean  = $values->sanitize( $field, array( array( 'link' => array( 'title' => 'No address' ) ) ) );
		$errors = $values->validate_tree( $field, $clean, true );
		$this->assertStringContainsString( 'New row 1', $errors[''] );

		$this->assertFalse( cfmb_update_field( 'items', array( array( 'link' => array( 'title' => 'No address' ) ) ), $this->post_id ) );
		$this->assertInstanceOf( Object_Ref::class, Object_Ref::from( $this->post_id ) );
	}
}
