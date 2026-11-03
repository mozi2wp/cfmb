import { fillLabel, suggestName } from '../../assets/src/builder/components/content/labels';

describe( 'fillLabel', () => {
	it( 'fills numbered placeholders like the server', () => {
		expect( fillLabel( 'Add New %1$s', 'Book', 'Books' ) ).toBe( 'Add New Book' );
		expect( fillLabel( 'No %4$s found.', 'Book', 'Books' ) ).toBe( 'No books found.' );
		expect( fillLabel( '&larr; Go to %2$s', 'Genre', 'Genres' ) ).toBe( '← Go to Genres' );
	} );
} );

describe( 'suggestName', () => {
	it( 'makes a key from a name', () => {
		expect( suggestName( 'Café Menu', 20 ) ).toBe( 'cafe_menu' );
		expect( suggestName( '  Event  ', 20 ) ).toBe( 'event' );
		expect( suggestName( 'A very long post type name', 10 ) ).toBe( 'a_very_lon' );
	} );
} );
