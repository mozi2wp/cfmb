<?php
/**
 * Presets.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Schema\Group;
use CFMB\Schema\Preset_Resolver;
use WP_UnitTestCase;

/**
 * Expanding presets into the fields they stand for.
 */
class PresetResolverTest extends WP_UnitTestCase {

	/**
	 * Source and user groups.
	 *
	 * @return array<string, Group>
	 */
	private function groups(): array {
		$source = new Group(
			array(
				'key'      => 'grp_source',
				'title'    => 'Hero',
				'settings' => array( 'preset_only' => true ),
				'fields'   => array(
					array( 'key' => 'fld_s1aaaa', 'name' => 'title', 'type' => 'text' ),
					array( 'key' => 'fld_s2aaaa', 'name' => 'image', 'type' => 'image' ),
				),
			)
		);

		$user = new Group(
			array(
				'key'    => 'grp_user01',
				'title'  => 'Page',
				'fields' => array(
					array(
						'key'      => 'fld_p1aaaa',
						'type'     => 'preset',
						'settings' => array( 'source' => 'grp_source' ),
					),
					array(
						'key'      => 'fld_p2aaaa',
						'name'     => 'banner',
						'type'     => 'preset',
						'settings' => array(
							'source' => 'grp_source',
							'naming' => 'prefixed',
							'fields' => array( 'fld_s1aaaa' ),
						),
					),
					array(
						'key'      => 'fld_p3aaaa',
						'type'     => 'preset',
						'settings' => array( 'source' => 'grp_user01' ),
					),
				),
			)
		);

		return array(
			'grp_source' => $source,
			'grp_user01' => $user,
		);
	}

	public function test_plain_and_prefixed_presets(): void {
		$resolved = ( new Preset_Resolver( $this->groups() ) )->resolve_all()['grp_user01'];
		$fields   = $resolved->fields();

		$this->assertSame( array( 'title', 'image', 'banner' ), array_map( static fn( $field ) => $field->name(), array_slice( $fields, 0, 3 ) ) );
		$this->assertSame( 'fld_p1aaaa__s1aaaa', $fields[0]->key() );
		$this->assertSame( 'group', $fields[2]->type() );
		$this->assertCount( 1, $fields[2]->sub_fields() );
	}

	public function test_preset_pointing_at_itself_stays_unresolved(): void {
		$resolved = ( new Preset_Resolver( $this->groups() ) )->resolve_all()['grp_user01'];

		$this->assertSame( 'preset', $resolved->fields()[3]->type() );
		$this->assertSame( 'preset', $resolved->to_array()['fields'][0]['type'] );
	}
}
