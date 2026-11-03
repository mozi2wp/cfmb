import { classicState, stateKey } from '../../assets/src/location-watch/state';

describe( 'live location state', () => {
	test( 'key ignores order of terms', () => {
		expect( stateKey( { template: 'a', terms: { category: [ 2, 1 ] } } ) ).toBe( stateKey( { template: 'a', terms: { category: [ 1, 2 ] } } ) );
		expect( stateKey( { template: 'a' } ) ).not.toBe( stateKey( { template: 'b' } ) );
	} );

	test( 'reads the classic form', () => {
		document.body.innerHTML = `<form id="post">
			<select id="page_template"><option value="default" selected>Default</option></select>
			<input type="radio" name="post_format" value="video" checked />
			<input type="checkbox" name="post_category[]" value="3" checked />
			<input type="checkbox" name="post_category[]" value="4" />
			<input type="checkbox" name="tax_input[genre][]" value="9" checked />
			<textarea name="tax_input[post_tag]">red, blue ,</textarea>
		</form>`;
		const state = classicState( document.getElementById( 'post' ) );
		expect( state.template ).toBe( '' );
		expect( state.format ).toBe( 'video' );
		expect( state.terms ).toEqual( { category: [ '3' ], genre: [ '9' ], post_tag: [ 'red', 'blue' ] } );
	} );
} );
