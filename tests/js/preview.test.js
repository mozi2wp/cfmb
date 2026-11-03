/**
 * @jest-environment jsdom
 */
import { htmlToElements, innerBlocksProps, parseStyle } from '../../assets/src/blocks/preview';

describe( 'parseStyle', () => {
	it( 'turns declarations into a style object', () => {
		expect( parseStyle( 'color: red; --gap: 4px; background-color:#fff' ) ).toEqual( {
			color: 'red',
			'--gap': '4px',
			backgroundColor: '#fff',
		} );
	} );
} );

describe( 'htmlToElements', () => {
	it( 'puts the inner blocks slot where the marker is and drops scripts and event handlers', () => {
		const slot = jest.fn( ( props, key ) => ( { slot: props, key } ) );
		const [ section ] = htmlToElements(
			'<section class="hero" onclick="x()"><cfmb-inner-blocks data-allowedblocks="[&quot;core/paragraph&quot;]" data-templatelock="false"></cfmb-inner-blocks><script>bad()</script></section>',
			slot
		);

		expect( section.type ).toBe( 'section' );
		expect( section.props.className ).toBe( 'hero' );
		expect( section.props.onclick ).toBeUndefined();
		expect( slot ).toHaveBeenCalledWith( { allowedBlocks: [ 'core/paragraph' ], templateLock: false }, 0 );
		expect( section.props.children[ 1 ] ).toBeNull();
	} );
} );

describe( 'innerBlocksProps', () => {
	it( 'ignores broken JSON', () => {
		const node = document.createElement( 'cfmb-inner-blocks' );
		node.setAttribute( 'data-template', '[oops' );
		node.setAttribute( 'data-orientation', 'horizontal' );
		expect( innerBlocksProps( node ) ).toEqual( { orientation: 'horizontal' } );
	} );
} );
