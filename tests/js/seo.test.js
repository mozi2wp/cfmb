import { contentFor, valueHtml } from '../../assets/src/seo/content';

const lookup = ( id ) => ( id === 7 ? { url: 'https://x/a.jpg', alt: 'A <cat>' } : null );

describe( 'SEO content', () => {
	test( 'values become HTML', () => {
		expect( valueHtml( { type: 'text' }, 'Fish & chips' ) ).toBe( '<p>Fish &amp; chips</p>\n' );
		expect( valueHtml( { type: 'wysiwyg' }, '<h2>Hi</h2>' ) ).toBe( '<h2>Hi</h2>\n' );
		expect( valueHtml( { type: 'image' }, 7, lookup ) ).toBe( '<img src="https://x/a.jpg" alt="A &lt;cat&gt;" />' );
		expect( valueHtml( { type: 'image' }, 8, lookup ) ).toBe( '' );
		expect( valueHtml( { type: 'text' }, '' ) ).toBe( '' );
	} );

	test( 'walks groups, rows and layouts, only wanted fields', () => {
		const fields = [
			{ key: 'a', type: 'text' },
			{ key: 'b', type: 'text' },
			{ key: 'g', type: 'group', fields: [ { key: 'g1', type: 'textarea' } ] },
			{ key: 'r', type: 'repeater', fields: [ { key: 'r1', type: 'text' } ] },
			{ key: 's', type: 'stacks', layouts: [ { name: 'hero', fields: [ { key: 's1', type: 'gallery' } ] } ] },
		];
		const values = { a: 'One', b: 'Skip', g: { g1: 'Two' }, r: { rows: [ { r1: 'Three' }, { r1: 'Four' } ] }, s: { rows: [ { _layout: 'hero', s1: [ 7 ] } ] } };
		const html = contentFor( fields, values, new Set( [ 'a', 'g1', 'r1', 's1' ] ), lookup );
		expect( html ).toBe( '<p>One</p>\n<p>Two</p>\n<p>Three</p>\n<p>Four</p>\n<img src="https://x/a.jpg" alt="A &lt;cat&gt;" />' );
	} );
} );
