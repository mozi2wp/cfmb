<?php
/**
 * Schema Sync.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Schema\Group_Repository;
use CFMB\Sync\Diff;
use CFMB\Sync\Portable;
use CFMB\Sync\Sync_Files;
use CFMB\Sync\Sync_Manager;
use WP_UnitTestCase;

/**
 * Files are written on save, changes are detected and merged.
 */
class SyncTest extends WP_UnitTestCase {

	/**
	 * A group to work with.
	 *
	 * @return array<string, mixed>
	 */
	private function group(): array {
		return array(
			'key'      => 'grp_synctest1',
			'title'    => 'Sync test',
			'active'   => true,
			'fields'   => array(
				array(
					'key'  => 'fld_synctest1',
					'name' => 'subtitle',
					'type' => 'text',
				),
			),
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
		);
	}

	public function tear_down(): void {
		cfmb()->get( Sync_Files::class )->delete( 'grp_synctest1' );
		parent::tear_down();
	}

	public function test_saving_writes_a_file_in_sync(): void {
		$sync = cfmb()->get( Sync_Manager::class );
		$sync->save( 'group', $this->group() );

		$this->assertFileExists( cfmb()->get( Sync_Files::class )->save_dir() . '/grp_synctest1.json' );
		$this->assertSame( Sync_Manager::SYNCED, $sync->row_for( 'group', 'grp_synctest1' )['status'] );
	}

	public function test_a_file_changed_outside_is_not_overwritten(): void {
		$sync  = cfmb()->get( Sync_Manager::class );
		$files = cfmb()->get( Sync_Files::class );
		$sync->save( 'group', $this->group() );

		$path             = $files->save_dir() . '/grp_synctest1.json';
		$data             = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data['title']    = 'Changed in git';
		$data['modified'] = time() + 10;
		file_put_contents( $path, wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$files->reset();

		$this->assertSame( Sync_Manager::FILE_CHANGED, $sync->row_for( 'group', 'grp_synctest1' )['status'] );

		$group             = cfmb()->get( Group_Repository::class )->find( 'grp_synctest1' )->to_array();
		$group['settings'] = array( 'description' => 'Changed here' );
		cfmb()->get( Group_Repository::class )->save( $group );

		$this->assertSame( 'Changed in git', json_decode( (string) file_get_contents( $path ), true )['title'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( Sync_Manager::CONFLICT, $sync->row_for( 'group', 'grp_synctest1' )['status'] );

		$row = $sync->merge( 'group', 'grp_synctest1', array( 'title' => 'file', 'settings' => 'db' ) );
		$this->assertSame( Sync_Manager::SYNCED, $row['status'] );
		$this->assertSame( 'Changed in git', cfmb()->get( Group_Repository::class )->find( 'grp_synctest1' )->title() );
	}

	public function test_field_merge_follows_choices(): void {
		$db   = Portable::group(
			array(
				'key'    => 'grp_m00000',
				'fields' => array(
					array( 'key' => 'fld_a00000', 'name' => 'a', 'type' => 'text' ),
					array( 'key' => 'fld_b00000', 'name' => 'b', 'type' => 'text' ),
				),
			)
		);
		$file = Portable::group(
			array(
				'key'    => 'grp_m00000',
				'fields' => array(
					array( 'key' => 'fld_b00000', 'name' => 'b2', 'type' => 'text' ),
					array( 'key' => 'fld_c00000', 'name' => 'c', 'type' => 'text' ),
				),
			)
		);

		$merged = Diff::merge( 'group', $db, $file, array( 'field:fld_a00000' => 'db' ) );

		$this->assertSame( array( 'a', 'b2', 'c' ), array_column( $merged['fields'], 'name' ) );
	}
}
