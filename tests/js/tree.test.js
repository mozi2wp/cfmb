/**
 * Builder field tree helpers.
 */
import { allLists, ancestors, moveField, namesByKey } from '../../assets/src/builder/tree';

const fields = [
	{ key: 'fld_a', name: 'a', type: 'text' },
	{ key: 'fld_g', name: 'g', type: 'group', fields: [ { key: 'fld_b', name: 'b', type: 'text' } ] },
	{ key: 'fld_s', name: 's', type: 'stacks', layouts: [ { key: 'lay_1', name: 'one', label: 'One', fields: [ { key: 'fld_c', name: 'c', type: 'repeater', fields: [] } ] } ] },
];

describe( 'tree', () => {
	it( 'moves a field between lists', () => {
		const moved = moveField( fields, 'fld_a', 'fld_s:lay_1', 0 );
		expect( moved.map( ( field ) => field.key ) ).toEqual( [ 'fld_g', 'fld_s' ] );
		expect( moved[ 1 ].layouts[ 0 ].fields.map( ( field ) => field.key ) ).toEqual( [ 'fld_a', 'fld_c' ] );
	} );

	it( 'refuses to move a field inside itself', () => {
		expect( moveField( fields, 'fld_s', 'fld_c', 0 ) ).toBe( fields );
	} );

	it( 'finds ancestors and lists', () => {
		expect( ancestors( fields, 'fld_c' ) ).toEqual( [ 'fld_s' ] );
		expect( allLists( fields ).map( ( list ) => list.id ) ).toEqual( [ '', 'fld_g', 'fld_s:lay_1', 'fld_c' ] );
		expect( namesByKey( fields ) ).toEqual( { fld_a: 'a', fld_g: 'g', fld_b: 'b', fld_s: 's', fld_c: 'c' } );
	} );
} );
