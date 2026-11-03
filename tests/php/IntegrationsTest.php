<?php
/**
 * Translation, GraphQL, WooCommerce and live location helpers.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Integrations\Graphql\Graphql;
use CFMB\Integrations\Translation\Field_Keys;
use CFMB\Location\Context;
use CFMB\Location\Rule_Registry;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Storage\Object_Ref;
use CFMB\Storage\Option_Store;
use WP_UnitTestCase;

/**
 * Behaviour that doesn't need WPML, Polylang, WPGraphQL or WooCommerce installed.
 */
class IntegrationsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		cfmb()->get( Group_Repository::class )->flush();

		$clean = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Book',
				'active'   => true,
				'fields'   => array(
					array( 'type' => 'text', 'label' => 'Subtitle', 'name' => 'subtitle' ),
					array( 'type' => 'number', 'label' => 'Price', 'name' => 'price' ),
					array(
						'type'         => 'repeater',
						'label'        => 'Items',
						'name'         => 'items',
						'integrations' => array( 'translation' => 'copy' ),
						'fields'       => array(
							array( 'type' => 'text', 'label' => 'Label', 'name' => 'label' ),
							array( 'type' => 'text', 'label' => 'Note', 'name' => 'note', 'integrations' => array( 'translation' => 'translate' ) ),
						),
					),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
				'settings' => array( 'graphql' => array( 'enabled' => true ) ),
			)
		);

		cfmb()->get( Group_Repository::class )->save( $clean );
		cfmb()->get( Field_Keys::class )->reset();
	}

	public function test_only_non_default_options_are_stored(): void {
		$group = cfmb()->get( Group_Repository::class )->all()[0];
		$items = $group->stored_fields()[2];

		$this->assertArrayNotHasKey( 'integrations', $group->stored_fields()[0] );
		$this->assertSame( array( 'translation' => 'copy' ), $items['integrations'] );
		$this->assertSame( array( 'enabled' => true, 'name' => '' ), $group->setting( 'graphql' ) );
	}

	public function test_keys_map_to_modes(): void {
		$keys = cfmb()->get( Field_Keys::class );

		$this->assertSame( 'translate', $keys->mode( 'subtitle' ) );
		$this->assertSame( 'copy', $keys->mode( 'price' ) );
		$this->assertSame( 'copy', $keys->mode( 'items' ) );
		$this->assertSame( 'copy', $keys->mode( 'items_3_label' ) );
		$this->assertSame( 'translate', $keys->mode( 'items_3_note' ) );
		$this->assertSame( '', $keys->mode( 'unrelated' ) );
	}

	public function test_options_keep_values_per_language(): void {
		$ref = new Object_Ref( Object_Ref::OPTION, 0, 'site' );
		update_option( 'cfmb_site_subtitle', 'Hello' );

		add_filter( 'cfmb/storage/option_language', static fn() => 'de' );
		$store = new Option_Store();

		$this->assertSame( 'Hello', $store->get( $ref, 'subtitle', false ), 'Falls back to the default language.' );
		$store->set( $ref, 'subtitle', 'Hallo', false );
		$this->assertSame( 'Hallo', get_option( 'cfmb_site_de_subtitle' ) );
		$this->assertSame( 'Hello', get_option( 'cfmb_site_subtitle' ) );

		remove_all_filters( 'cfmb/storage/option_language' );
		$this->assertSame( 'Hello', $store->get( $ref, 'subtitle', false ) );
	}

	public function test_graphql_casts(): void {
		$this->assertSame( 5, Graphql::cast( '5', 'Int' ) );
		$this->assertSame( array( 1, 2 ), Graphql::cast( array( '1', '2' ), array( 'list_of' => 'Int' ) ) );
		$this->assertSame( '{"a":1}', Graphql::cast( array( 'a' => 1 ), 'String' ) );
		$this->assertSame( 'heroTitle', Graphql::field_name( 'hero_title' ) );
	}

	public function test_form_values_win_over_saved_ones(): void {
		$post = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$rule = cfmb()->get( Rule_Registry::class )->get( 'page_template' );

		$this->assertTrue( $rule->match( Context::for_post( $post, 'page' ), '==', 'default' ) );
		$this->assertTrue( $rule->match( Context::for_post( $post, 'page' )->with( array( 'template' => 'wide.php' ) ), '==', 'wide.php' ) );
	}

	public function test_location_match_route(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post    = self::factory()->post->create();
		$group   = cfmb()->get( Group_Repository::class )->all()[0];
		$request = new \WP_REST_Request( 'POST', '/cfmb/v1/location/match' );
		$request->set_body_params(
			array(
				'post_id'   => $post,
				'post_type' => 'post',
				'groups'    => array( $group->key() ),
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $group->key() ), $response->get_data()['groups'] );
	}
}
