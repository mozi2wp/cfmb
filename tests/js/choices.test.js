import { formatChoiceLines, parseChoiceLines } from '../../assets/src/shared/choices';
import { toFieldName } from '../../assets/src/shared/keys';

describe( 'choices', () => {
	it( 'parses value : label lines and drops duplicates', () => {
		expect( parseChoiceLines( 'red : Red\nblue\n\nred : Again' ) ).toEqual( [
			{ value: 'red', label: 'Red' },
			{ value: 'blue', label: 'blue' },
		] );
	} );

	it( 'round-trips', () => {
		const choices = [ { value: 'a', label: 'Apple' }, { value: 'b', label: 'b' } ];
		expect( parseChoiceLines( formatChoiceLines( choices ) ) ).toEqual( choices );
	} );
} );

describe( 'toFieldName', () => {
	it( 'matches the server rules', () => {
		expect( toFieldName( 'Prix Été' ) ).toBe( 'prix_ete' );
		expect( toFieldName( '  Hero image!  ', true ) ).toBe( 'hero_image' );
		expect( toFieldName( '___' ) ).toBe( '' );
	} );
} );
