import { fieldList, isCurrentObject, pick } from '../../assets/src/bindings/source';

describe( 'pick', () => {
	const parts = { html: '<a>x</a>', text: 'x', url: 'https://example.com', alt: 'A', title: 'T', id: 3 };

	it( 'matches attributes to value parts like the server', () => {
		expect( pick( parts, 'url' ) ).toBe( 'https://example.com' );
		expect( pick( parts, 'content' ) ).toBe( '<a>x</a>' );
		expect( pick( parts, 'text' ) ).toBe( 'x' );
		expect( pick( parts, 'id' ) ).toBe( 3 );
		expect( pick( null, 'content' ) ).toBeUndefined();
	} );
} );

describe( 'fieldList', () => {
	it( 'lists post type fields, then options page fields', () => {
		const list = fieldList(
			{
				postTypes: { post: [ { name: 'price', label: 'Price', type: 'text' } ] },
				options: [ { object: 'option:site', title: 'Site', fields: [ { name: 'phone', label: 'Phone' } ] } ],
			},
			'post'
		);
		expect( list ).toEqual( [
			{ label: 'Price', type: 'string', args: { field: 'price' } },
			{ label: 'Site: Phone', type: 'string', args: { field: 'phone', object: 'option:site' } },
		] );
	} );

	it( 'knows which objects mean the current post', () => {
		expect( isCurrentObject( '' ) ).toBe( true );
		expect( isCurrentObject( 'post' ) ).toBe( true );
		expect( isCurrentObject( 'option' ) ).toBe( false );
	} );
} );
