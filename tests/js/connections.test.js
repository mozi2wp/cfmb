/**
 * Connection box list helpers.
 */
import { moveItem, toggleItem } from '../../assets/src/connections/list';

const a = { id: 1 };
const b = { id: 2 };
const c = { id: 3 };

describe( 'toggleItem', () => {
	it( 'adds an item', () => expect( toggleItem( [ a ], b, 0 ) ).toEqual( [ a, b ] ) );
	it( 'keeps the list when the item is there', () => {
		const list = [ a ];
		expect( toggleItem( list, a, 0 ) ).toBe( list );
	} );
	it( 'replaces with a limit of one', () => expect( toggleItem( [ a ], b, 1 ) ).toEqual( [ b ] ) );
	it( 'stops at the limit', () => {
		const list = [ a, b ];
		expect( toggleItem( list, c, 2 ) ).toBe( list );
	} );
} );

describe( 'moveItem', () => {
	it( 'moves down', () => expect( moveItem( [ a, b, c ], 0, 1 ) ).toEqual( [ b, a, c ] ) );
	it( 'ignores moves past the ends', () => {
		const list = [ a, b ];
		expect( moveItem( list, 0, -1 ) ).toBe( list );
		expect( moveItem( list, 1, 1 ) ).toBe( list );
	} );
} );
