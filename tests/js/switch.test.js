/**
 * Switch Assistant selection helpers.
 */
import { initialPicks, runAll, togglePick } from '../../assets/src/builder/components/switch/picks';

const sections = [
	{ list: 'groups', pick: 'groups' },
	{ list: 'postTypes', pick: 'post_types' },
];

describe( 'initialPicks', () => {
	it( 'picks everything not imported yet', () => {
		const scan = { groups: [ { key: 'g1' }, { key: 'g2', imported: true } ], postTypes: [] };
		expect( initialPicks( scan, sections ) ).toEqual( { groups: [ 'g1' ], post_types: [] } );
	} );
} );

describe( 'togglePick', () => {
	it( 'adds once and removes', () => {
		let picks = togglePick( { groups: [] }, 'groups', 'g1', true );
		picks = togglePick( picks, 'groups', 'g1', true );
		expect( picks.groups ).toEqual( [ 'g1' ] );
		expect( togglePick( picks, 'groups', 'g1', false ).groups ).toEqual( [] );
	} );
} );

describe( 'runAll', () => {
	it( 'calls until the status is reached', async () => {
		let calls = 0;
		const seen = [];
		const result = await runAll( async () => ( { status: ++calls < 3 ? 'converting' : 'done' } ), 'done', ( step ) => seen.push( step.status ) );
		expect( result.status ).toBe( 'done' );
		expect( seen ).toEqual( [ 'converting', 'converting', 'done' ] );
	} );

	it( 'gives up after the limit', async () => {
		await expect( runAll( async () => ( { status: 'converting' } ), 'done', () => {}, 3 ) ).rejects.toThrow();
	} );
} );
