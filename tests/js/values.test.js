/**
 * Editor value shapes for Group, Repeater and Stacks fields.
 */
import { cloneRow, fieldSignature, newRow, prepare, remapRow, serialize } from '../../assets/src/fields/values';
import { validateTree } from '../../assets/src/fields/validation';

const repeater = {
	key: 'fld_rep',
	type: 'repeater',
	settings: { min: 1, pagination: true },
	fields: [
		{ key: 'fld_title', name: 'title', type: 'text', required: true, settings: {} },
		{ key: 'fld_img', name: 'image', type: 'image', settings: {} },
		{ key: 'fld_inner', name: 'inner', type: 'repeater', settings: {}, fields: [ { key: 'fld_x', name: 'x', type: 'text', settings: {} } ] },
	],
};

const stacks = {
	key: 'fld_stk',
	type: 'stacks',
	settings: {},
	layouts: [ { key: 'lay_a', name: 'hero', label: 'Hero', max: 1, fields: [ { key: 'fld_h', name: 'h', type: 'text', settings: {} } ] } ],
};

describe( 'prepare', () => {
	it( 'expands a paged value into placeholders for rows not loaded', () => {
		const value = prepare( repeater, { paged: true, total: 3, perPage: 1, layouts: [], rows: [ { _from: 0, fld_title: 'A', fld_inner: [] } ] } );
		expect( value.paged ).toBe( true );
		expect( value.rows ).toHaveLength( 3 );
		expect( value.rows[ 0 ].fld_title ).toBe( 'A' );
		expect( value.rows[ 0 ].fld_inner.rows ).toEqual( [] );
		expect( value.rows[ 2 ]._unloaded ).toBe( true );
		expect( value.rows[ 2 ]._from ).toBe( 2 );
	} );
} );

describe( 'serialize', () => {
	it( 'sends unloaded rows as references and media as IDs', () => {
		const value = prepare( repeater, { paged: true, total: 2, perPage: 1, rows: [ { _from: 0, fld_title: 'A', fld_img: { id: 7, url: 'x' }, fld_inner: [ { _from: 0, fld_x: 'y' } ] } ] } );
		expect( serialize( repeater, value ) ).toEqual( {
			paged: 1,
			rows: [ { _from: 0, fld_title: 'A', fld_img: 7, fld_inner: [ { _from: 0, fld_x: 'y' } ] }, { _keep: 1, _from: 1 } ],
		} );
	} );

	it( 'keeps the layout of Stacks rows', () => {
		const value = prepare( stacks, [ { _from: 0, _layout: 'hero', fld_h: 'Hi' } ] );
		expect( serialize( stacks, value ) ).toEqual( [ { _layout: 'hero', _from: 0, fld_h: 'Hi' } ] );
	} );
} );

describe( 'rows', () => {
	it( 'creates new rows with defaults and copies rows without their origin', () => {
		const row = newRow( stacks, 'hero' );
		expect( row._layout ).toBe( 'hero' );
		expect( row.fld_h ).toBe( '' );
		const copy = cloneRow( repeater, { _uid: 'a', _from: 3, fld_title: 'A', fld_inner: { rows: [ { _uid: 'b', _from: 0, fld_x: 'y' } ] } } );
		expect( copy._from ).toBeUndefined();
		expect( copy._uid ).not.toBe( 'a' );
		expect( copy.fld_inner.rows[ 0 ]._from ).toBeUndefined();
	} );
} );

describe( 'validateTree', () => {
	it( 'reports problems with the same paths as the server', () => {
		const value = prepare( repeater, [ { fld_title: '' }, { fld_title: 'ok' } ] );
		const problems = validateTree( repeater, value, true, 'fld_rep' ).map( ( item ) => item.path );
		expect( problems ).toEqual( [ 'fld_rep.0.fld_title' ] );
	} );

	it( 'checks the minimum number of rows only when publishing', () => {
		const empty = prepare( repeater, [] );
		expect( validateTree( repeater, empty, true, 'fld_rep' ) ).toHaveLength( 1 );
		expect( validateTree( repeater, empty, false, 'fld_rep' ) ).toHaveLength( 0 );
	} );

	it( 'checks per-layout limits', () => {
		const value = prepare( stacks, [ { _layout: 'hero' }, { _layout: 'hero' } ] );
		expect( validateTree( stacks, value, false, 'fld_stk' )[ 0 ].message ).toContain( 'Hero' );
	} );
} );

describe( 'pasting between fields', () => {
	const twin = JSON.parse( JSON.stringify( repeater ).replace( /fld_/g, 'fld_t' ) );

	it( 'matches fields by names and types, not keys', () => {
		expect( fieldSignature( twin ) ).toBe( fieldSignature( repeater ) );
		expect( fieldSignature( stacks ) ).not.toBe( fieldSignature( repeater ) );
	} );

	it( 're-keys rows for the other field', () => {
		const row = remapRow( repeater, twin, { _uid: 'a', _from: 2, fld_title: 'A', fld_img: null, fld_inner: { rows: [ { fld_x: 'y' } ] } } );
		expect( row.fld_ttitle ).toBe( 'A' );
		expect( row.fld_tinner.rows[ 0 ].fld_tx ).toBe( 'y' );
		expect( row._from ).toBeUndefined();
		expect( row.fld_title ).toBeUndefined();
	} );
} );
