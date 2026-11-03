<?php
/**
 * Location matching.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Location\Context;
use CFMB\Schema\Group;
use CFMB\Location\Matcher;
use WP_UnitTestCase;

/**
 * Rules decide where groups show.
 */
class LocationMatcherTest extends WP_UnitTestCase {

	/**
	 * Group with the given location.
	 *
	 * @param array<int, array<int, array<string, string>>> $location Rules.
	 * @return Group
	 */
	private function group( array $location ): Group {
		return new Group(
			array(
				'key'      => 'grp_aaaaaaaaaa',
				'title'    => 'Test',
				'location' => $location,
			)
		);
	}

	public function test_or_of_and(): void {
		$matcher = cfmb()->get( Matcher::class );
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$group = $this->group(
			array(
				array(
					array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ),
					array( 'param' => 'post_status', 'operator' => '==', 'value' => 'publish' ),
				),
				array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'category' ) ),
			)
		);

		$this->assertTrue( $matcher->matches( $group, Context::for_post( $page, 'page' ) ) );
		$this->assertTrue( $matcher->matches( $group, Context::for_term( 'category' ) ) );
		$this->assertFalse( $matcher->matches( $group, Context::for_term( 'post_tag' ) ) );
	}

	public function test_not_equal_rules_never_match_other_screens(): void {
		$matcher = cfmb()->get( Matcher::class );
		$group   = $this->group( array( array( array( 'param' => 'post_type', 'operator' => '!=', 'value' => 'page' ) ) ) );

		$this->assertTrue( $matcher->matches( $group, Context::for_post( 0, 'post' ) ) );
		$this->assertFalse( $matcher->matches( $group, Context::for_user( 1, 'edit' ) ) );
	}

	public function test_empty_location_matches_nothing(): void {
		$this->assertFalse( cfmb()->get( Matcher::class )->matches( $this->group( array() ), Context::for_post( 0, 'post' ) ) );
	}
}
