/**
 * Must give the same answers as tests/php/ConditionsTest.php.
 */
import { compare, isVisible } from '../../assets/src/shared/conditions';

describe( 'compare', () => {
	test.each( [
		[ 'red', '==', 'red', true ],
		[ 5, '==', '5', true ],
		[ '1', '==', '1', true ],
		[ [ 'a', 'b' ], '==', 'b', true ],
		[ [ 'a' ], '!=', 'b', true ],
		[ '', 'empty', '', true ],
		[ [], 'empty', '', true ],
		[ '0', 'empty', '', false ],
		[ 'x', 'not_empty', '', true ],
		[ 'hello world', 'contains', 'world', true ],
		[ 'hello', 'contains', '', false ],
		[ '10', '>', '9', true ],
		[ 'abc', '<', '9', false ],
		[ null, '==', 'a', false ],
	] )( '%p %s %p is %p', ( actual, operator, expected, result ) => {
		expect( compare( actual, operator, expected ) ).toBe( result );
	} );
} );

describe( 'isVisible', () => {
	const field = {
		key: 'fld_target0001',
		conditions: [
			[
				{ field: 'fld_a000000001', operator: '==', value: 'yes' },
				{ field: 'fld_b000000001', operator: 'not_empty', value: '' },
			],
			[ { field: 'fld_c000000001', operator: '==', value: '1' } ],
		],
	};

	it( 'uses OR of AND sets', () => {
		expect( isVisible( field, { fld_a000000001: 'yes', fld_b000000001: 'x' } ) ).toBe( true );
		expect( isVisible( field, { fld_a000000001: 'yes', fld_b000000001: '' } ) ).toBe( false );
		expect( isVisible( field, { fld_c000000001: '1' } ) ).toBe( true );
		expect( isVisible( { key: 'fld_free000001' }, {} ) ).toBe( true );
	} );

	it( 'compares media fields by attachment ID', () => {
		const media = { key: 'x', conditions: [ [ { field: 'img', operator: 'not_empty', value: '' } ] ] };
		expect( isVisible( media, { img: { id: 12, url: 'a.jpg' } } ) ).toBe( true );
		expect( isVisible( media, { img: null } ) ).toBe( false );
	} );
} );
