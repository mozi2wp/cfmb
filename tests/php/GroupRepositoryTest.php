<?php
/**
 * Field Group storage.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use WP_UnitTestCase;

/**
 * Save, find, trash, restore and duplicate.
 */
class GroupRepositoryTest extends WP_UnitTestCase {

	/**
	 * Groups are cached per request; tests roll back the database between runs.
	 */
	public function set_up(): void {
		parent::set_up();
		cfmb()->get( Group_Repository::class )->flush();
	}

	/**
	 * Save a sample group.
	 *
	 * @param bool $active Active flag.
	 * @return \CFMB\Schema\Group
	 */
	private function save_sample( bool $active = true ) {
		$clean = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Event',
				'active'   => $active,
				'fields'   => array(
					array( 'key' => 'fld_aaaaaaaaaa', 'type' => 'toggle', 'label' => 'Online', 'name' => 'online' ),
					array(
						'key'        => 'fld_bbbbbbbbbb',
						'type'       => 'url',
						'label'      => 'Link',
						'name'       => 'link',
						'conditions' => array( array( array( 'field' => 'fld_aaaaaaaaaa', 'operator' => '==', 'value' => '1' ) ) ),
					),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			)
		);

		return cfmb()->get( Group_Repository::class )->save( $clean );
	}

	public function test_save_and_find(): void {
		$saved = $this->save_sample();
		$found = cfmb()->get( Group_Repository::class )->find( $saved->key() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Event', $found->title() );
		$this->assertCount( 2, $found->fields() );
		$this->assertTrue( $found->active() );
	}

	public function test_kses_does_not_break_json_for_editors(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		kses_init_filters();

		$saved = $this->save_sample();

		$this->assertCount( 2, cfmb()->get( Group_Repository::class )->find( $saved->key() )->fields() );
		kses_remove_filters();
	}

	public function test_trash_and_restore_keep_inactive_state(): void {
		$repo  = cfmb()->get( Group_Repository::class );
		$saved = $this->save_sample( false );

		$this->assertTrue( $repo->trash( $saved->key() ) );
		$this->assertCount( 0, array_filter( $repo->all(), static fn( $g ) => $g->key() === $saved->key() ) );
		$this->assertCount( 1, $repo->trashed() );

		$this->assertTrue( $repo->restore( $saved->key() ) );
		$restored = $repo->find( $saved->key() );

		$this->assertNotNull( $restored );
		$this->assertFalse( $restored->active() );
	}

	public function test_duplicate_remaps_condition_keys(): void {
		$repo  = cfmb()->get( Group_Repository::class );
		$saved = $this->save_sample();
		$copy  = $repo->duplicate( $saved->key() );

		$this->assertNotSame( $saved->key(), $copy->key() );
		$this->assertFalse( $copy->active() );

		$fields = $copy->fields();
		$this->assertNotSame( 'fld_aaaaaaaaaa', $fields[0]->key() );
		$this->assertSame( $fields[0]->key(), $fields[1]->conditions()[0][0]['field'] );
	}
}
