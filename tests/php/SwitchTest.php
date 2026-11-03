<?php
/**
 * Switch Assistant: ACF definitions and values, with undo.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Switching\Switch_Assistant;
use WP_UnitTestCase;

defined( 'ABSPATH' ) || exit;

/**
 * Runs in wp-env with the plugin active and ACF not installed.
 */
class SwitchTest extends WP_UnitTestCase {

	/**
	 * A group with a checkbox and a date picker, stored like ACF does.
	 *
	 * @return int Post with values.
	 */
	private function seed(): int {
		$group = wp_insert_post(
			array(
				'post_type'    => 'acf-field-group',
				'post_status'  => 'publish',
				'post_name'    => 'group_65a1b2c3d4e5f',
				'post_title'   => 'Book details',
				'post_content' => serialize( array( 'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- ACF's format.
			)
		);

		foreach ( array(
			array( 'field_65a1b2c3d4e60', 'tags', array( 'type' => 'checkbox', 'choices' => array( 'red' => 'Red', 'blue' => 'Blue' ) ) ),
			array( 'field_65a1b2c3d4e61', 'released', array( 'type' => 'date_picker' ) ),
		) as $order => $field ) {
			wp_insert_post(
				array(
					'post_type'    => 'acf-field',
					'post_status'  => 'publish',
					'post_parent'  => $group,
					'menu_order'   => $order,
					'post_name'    => $field[0],
					'post_excerpt' => $field[1],
					'post_title'   => ucfirst( $field[1] ),
					'post_content' => serialize( $field[2] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- ACF's format.
				)
			);
		}

		$post = self::factory()->post->create();
		update_post_meta( $post, 'tags', array( 'red', 'blue' ) );
		update_post_meta( $post, '_tags', 'field_65a1b2c3d4e60' );
		update_post_meta( $post, 'released', '20261015' );
		update_post_meta( $post, '_released', 'field_65a1b2c3d4e61' );

		return $post;
	}

	/**
	 * Import, convert, check, undo.
	 *
	 * @return void
	 */
	public function test_import_convert_undo(): void {
		$post      = $this->seed();
		$assistant = cfmb()->get( Switch_Assistant::class );
		$run       = $assistant->import( array( 'groups' => array( 'group_65a1b2c3d4e5f' ) ) );

		$this->assertSame( 1, $run['created'] );

		do {
			$step = $assistant->convert( $run['id'] );
		} while ( 'done' !== $step['status'] );

		$this->assertSame( array( 'red', 'blue' ), get_post_meta( $post, 'tags' ) );
		$this->assertSame( '2026-10-15', get_post_meta( $post, 'released', true ) );
		$this->assertSame( array( 'red', 'blue' ), cfmb_get_field( 'tags', $post, false ) );

		do {
			$step = $assistant->undo( $run['id'] );
		} while ( 'undone' !== $step['status'] );

		$this->assertSame( array( array( 'red', 'blue' ) ), get_post_meta( $post, 'tags' ) );
		$this->assertSame( '20261015', get_post_meta( $post, 'released', true ) );
	}
}
