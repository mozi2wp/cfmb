import { blockSettings, blockSlug } from '../../assets/src/builder/blockSettings';

describe( 'blockSlug', () => {
	it( 'makes block names like the server', () => {
		expect( blockSlug( 'Café Hero!' ) ).toBe( 'cafe-hero' );
		expect( blockSlug( '  Big   Banner ' ) ).toBe( 'big-banner' );
		expect( blockSlug( '2 columns' ) ).toBe( '' );
	} );
} );

describe( 'blockSettings', () => {
	it( 'fills defaults, also for the empty array PHP sends', () => {
		const settings = blockSettings( [] );
		expect( settings.enabled ).toBe( false );
		expect( settings.supports.multiple ).toBe( true );
		expect( blockSettings( { supports: { align: true } } ).supports ).toMatchObject( { align: true, inserter: true } );
	} );
} );
