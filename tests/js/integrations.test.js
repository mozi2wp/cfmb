import { defaultTranslation, nextIntegrations, readIntegrations } from '../../assets/src/builder/integrations';

describe( 'field integration options', () => {
	test( 'defaults follow the type', () => {
		expect( defaultTranslation( 'text' ) ).toBe( 'translate' );
		expect( defaultTranslation( 'number' ) ).toBe( 'copy' );
		expect( readIntegrations( { type: 'text' } ) ).toEqual( { translation: '', seo: true, graphql: true } );
		expect( readIntegrations( { type: 'number' } ).seo ).toBe( false );
	} );

	test( 'only non-defaults are stored', () => {
		expect( nextIntegrations( { type: 'text' }, { seo: true } ) ).toBeUndefined();
		expect( nextIntegrations( { type: 'text' }, { seo: false } ) ).toEqual( { seo: false } );
		expect( nextIntegrations( { type: 'number', integrations: { seo: true } }, { translation: 'copy_once' } ) ).toEqual( { translation: 'copy_once', seo: true } );
		expect( nextIntegrations( { type: 'text', integrations: { translation: 'copy' } }, { translation: '' } ) ).toBeUndefined();
		expect( nextIntegrations( { type: 'text' }, { graphql: false } ) ).toEqual( { graphql: false } );
	} );
} );
