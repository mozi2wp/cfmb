<?php
/**
 * Conditional logic.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Location\Conditions;
use CFMB\Schema\Field;
use WP_UnitTestCase;

/**
 * Must give the same answers as assets/src/shared/conditions.js (see tests/js).
 */
class ConditionsTest extends WP_UnitTestCase {

	/**
	 * Operator cases shared with the JS tests.
	 *
	 * @return array<string, array{0: mixed, 1: string, 2: string, 3: bool}>
	 */
	public function compare_cases(): array {
		return array(
			'equal string'              => array( 'red', '==', 'red', true ),
			'equal number as string'    => array( 5, '==', '5', true ),
			'toggle on'                 => array( '1', '==', '1', true ),
			'list includes value'       => array( array( 'a', 'b' ), '==', 'b', true ),
			'list lacks value'          => array( array( 'a' ), '!=', 'b', true ),
			'empty string is empty'     => array( '', 'empty', '', true ),
			'empty list is empty'       => array( array(), 'empty', '', true ),
			'zero is not empty'         => array( '0', 'empty', '', false ),
			'has any value'             => array( 'x', 'not_empty', '', true ),
			'contains substring'        => array( 'hello world', 'contains', 'world', true ),
			'contains empty is false'   => array( 'hello', 'contains', '', false ),
			'greater than'              => array( '10', '>', '9', true ),
			'less than non numeric'     => array( 'abc', '<', '9', false ),
			'null is not equal to text' => array( null, '==', 'a', false ),
		);
	}

	/**
	 * Compare operators.
	 *
	 * @dataProvider compare_cases
	 *
	 * @param mixed  $actual   Value.
	 * @param string $operator Operator.
	 * @param string $expected Rule value.
	 * @param bool   $result   Expected result.
	 */
	public function test_compare( $actual, string $operator, string $expected, bool $result ): void {
		$this->assertSame( $result, Conditions::compare( $actual, $operator, $expected ) );
	}

	/**
	 * OR of AND sets.
	 */
	public function test_visible_or_of_and(): void {
		$field = new Field(
			array(
				'key'        => 'fld_target0001',
				'conditions' => array(
					array(
						array( 'field' => 'fld_a000000001', 'operator' => '==', 'value' => 'yes' ),
						array( 'field' => 'fld_b000000001', 'operator' => 'not_empty', 'value' => '' ),
					),
					array(
						array( 'field' => 'fld_c000000001', 'operator' => '==', 'value' => '1' ),
					),
				),
			)
		);

		$this->assertTrue( Conditions::visible( $field, array( 'fld_a000000001' => 'yes', 'fld_b000000001' => 'x' ) ) );
		$this->assertFalse( Conditions::visible( $field, array( 'fld_a000000001' => 'yes', 'fld_b000000001' => '' ) ) );
		$this->assertTrue( Conditions::visible( $field, array( 'fld_c000000001' => '1' ) ) );
		$this->assertTrue( Conditions::visible( new Field( array( 'key' => 'fld_free000001' ) ), array() ) );
	}
}
