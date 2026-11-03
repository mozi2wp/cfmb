<?php
/**
 * Field Group input sanitizing.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Schema\Group_Sanitizer;
use WP_UnitTestCase;

/**
 * Builder input becomes a clean group or a clear error.
 */
class GroupSanitizerTest extends WP_UnitTestCase {

	/**
	 * Sanitizer under test.
	 *
	 * @return Group_Sanitizer
	 */
	private function sanitizer(): Group_Sanitizer {
		return cfmb()->get( Group_Sanitizer::class );
	}

	/**
	 * Minimal valid input.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<string, mixed>
	 */
	private function input( array $fields ): array {
		return array(
			'title'    => 'Product details',
			'fields'   => $fields,
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
		);
	}

	public function test_requires_title(): void {
		$this->assertWPError( $this->sanitizer()->sanitize( array( 'title' => '  ' ) ) );
	}

	public function test_normalizes_names_and_keeps_valid_keys(): void {
		$group = $this->sanitizer()->sanitize(
			$this->input(
				array(
					array( 'key' => 'fld_aaaaaaaaaa', 'type' => 'text', 'label' => 'Price', 'name' => 'Prix Été' ),
					array( 'key' => 'not-a-key', 'type' => 'number', 'label' => 'Stock', 'name' => 'stock' ),
				)
			)
		);

		$this->assertIsArray( $group );
		$this->assertSame( 'fld_aaaaaaaaaa', $group['fields'][0]['key'] );
		$this->assertSame( 'prix_ete', $group['fields'][0]['name'] );
		$this->assertMatchesRegularExpression( '/^fld_[a-z0-9]{10}$/', $group['fields'][1]['key'] );
	}

	public function test_rejects_duplicate_names(): void {
		$result = $this->sanitizer()->sanitize(
			$this->input(
				array(
					array( 'type' => 'text', 'label' => 'A', 'name' => 'same' ),
					array( 'type' => 'text', 'label' => 'B', 'name' => 'same' ),
				)
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'cfmb_invalid_field', $result->get_error_code() );
	}

	public function test_rejects_reserved_names(): void {
		$this->assertWPError(
			$this->sanitizer()->sanitize( $this->input( array( array( 'type' => 'text', 'label' => 'Lock', 'name' => '_edit_lock' ) ) ) )
		);
	}

	public function test_layout_fields_need_no_name(): void {
		$group = $this->sanitizer()->sanitize( $this->input( array( array( 'type' => 'tab', 'label' => 'General', 'name' => 'ignored' ) ) ) );

		$this->assertIsArray( $group );
		$this->assertSame( '', $group['fields'][0]['name'] );
	}

	public function test_drops_conditions_on_missing_or_self_fields(): void {
		$group = $this->sanitizer()->sanitize(
			$this->input(
				array(
					array( 'key' => 'fld_aaaaaaaaaa', 'type' => 'toggle', 'label' => 'On', 'name' => 'on' ),
					array(
						'key'        => 'fld_bbbbbbbbbb',
						'type'       => 'text',
						'label'      => 'Text',
						'name'       => 'text',
						'conditions' => array(
							array(
								array( 'field' => 'fld_aaaaaaaaaa', 'operator' => '==', 'value' => '1' ),
								array( 'field' => 'fld_bbbbbbbbbb', 'operator' => '==', 'value' => 'x' ),
								array( 'field' => 'fld_missing000', 'operator' => '==', 'value' => 'x' ),
								array( 'field' => 'fld_aaaaaaaaaa', 'operator' => 'eval', 'value' => 'x' ),
							),
						),
					),
				)
			)
		);

		$this->assertSame(
			array( array( array( 'field' => 'fld_aaaaaaaaaa', 'operator' => '==', 'value' => '1' ) ) ),
			$group['fields'][1]['conditions']
		);
	}

	public function test_drops_unknown_location_rules(): void {
		$input             = $this->input( array() );
		$input['location'] = array( array( array( 'param' => 'nope', 'operator' => '==', 'value' => 'x' ) ) );

		$group = $this->sanitizer()->sanitize( $input );

		$this->assertSame( array(), $group['location'] );
	}

	public function test_choices_accept_lines(): void {
		$group = $this->sanitizer()->sanitize(
			$this->input(
				array(
					array(
						'type'     => 'select',
						'label'    => 'Color',
						'name'     => 'color',
						'settings' => array( 'choices' => "red : Red\nblue\nred : Duplicate" ),
					),
				)
			)
		);

		$this->assertSame(
			array(
				array( 'value' => 'red', 'label' => 'Red' ),
				array( 'value' => 'blue', 'label' => 'blue' ),
			),
			$group['fields'][0]['settings']['choices']
		);
	}
}
