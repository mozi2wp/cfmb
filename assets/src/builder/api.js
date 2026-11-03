/**
 * REST calls to cfmb/v1.
 */
import apiFetch from '@wordpress/api-fetch';

const BASE = '/cfmb/v1';

export const listGroups = ( status = 'any' ) => apiFetch( { path: `${ BASE }/groups?status=${ status }` } );
export const getGroup = ( key ) => apiFetch( { path: `${ BASE }/groups/${ key }` } );
export const createGroup = ( group ) => apiFetch( { path: `${ BASE }/groups`, method: 'POST', data: group } );
export const updateGroup = ( group ) => apiFetch( { path: `${ BASE }/groups/${ group.key }`, method: 'PUT', data: group } );
export const trashGroup = ( key ) => apiFetch( { path: `${ BASE }/groups/${ key }`, method: 'DELETE' } );
export const deleteGroup = ( key ) => apiFetch( { path: `${ BASE }/groups/${ key }?force=true`, method: 'DELETE' } );
export const restoreGroup = ( key ) => apiFetch( { path: `${ BASE }/groups/${ key }/restore`, method: 'POST' } );
export const duplicateGroup = ( key ) => apiFetch( { path: `${ BASE }/groups/${ key }/duplicate`, method: 'POST' } );

const choiceCache = new Map();

/**
 * Choices for a location rule, fetched once per page load.
 *
 * @param {string} param Rule key.
 * @return {Promise<Array<{value: string, label: string}>>} Choices.
 */
export function ruleChoices( param ) {
	if ( ! choiceCache.has( param ) ) {
		const request = apiFetch( { path: `${ BASE }/location/choices?param=${ encodeURIComponent( param ) }` } ).catch( ( error ) => {
			choiceCache.delete( param );
			throw error;
		} );
		choiceCache.set( param, request );
	}
	return choiceCache.get( param );
}

let groupList = null;

/**
 * Every stored group, fetched once per page load. Used to pick preset sources.
 *
 * @return {Promise<Array>} Groups.
 */
export function presetSources() {
	if ( ! groupList ) {
		groupList = listGroups( 'any' ).catch( ( error ) => {
			groupList = null;
			throw error;
		} );
	}
	return groupList;
}

const SEGMENTS = { post_type: 'post-types', taxonomy: 'taxonomies', options_page: 'options-pages', connection: 'connections' };
const contentBase = ( kind ) => `${ BASE }/content/${ SEGMENTS[ kind ] }`;

export const listContent = ( kind, status = 'any' ) => apiFetch( { path: `${ contentBase( kind ) }?status=${ status }` } );
export const getContent = ( kind, key ) => apiFetch( { path: `${ contentBase( kind ) }/${ key }` } );
export const createContent = ( kind, item ) => apiFetch( { path: contentBase( kind ), method: 'POST', data: item } );
export const updateContent = ( kind, item, moveContent = false ) =>
	apiFetch( { path: `${ contentBase( kind ) }/${ item.key }`, method: 'PUT', data: { ...item, move_content: moveContent } } );
export const trashContent = ( kind, key ) => apiFetch( { path: `${ contentBase( kind ) }/${ key }`, method: 'DELETE' } );
export const deleteContent = ( kind, key ) => apiFetch( { path: `${ contentBase( kind ) }/${ key }?force=true`, method: 'DELETE' } );
export const restoreContent = ( kind, key ) => apiFetch( { path: `${ contentBase( kind ) }/${ key }/restore`, method: 'POST' } );
export const duplicateContent = ( kind, key ) => apiFetch( { path: `${ contentBase( kind ) }/${ key }/duplicate`, method: 'POST' } );

export const syncStatus = () => apiFetch( { path: `${ BASE }/sync` } );
export const syncDiff = ( kind, key ) => apiFetch( { path: `${ BASE }/sync/${ kind }/${ key }/diff` } );
export const syncPush = ( items ) => apiFetch( { path: `${ BASE }/sync/push`, method: 'POST', data: { items } } );
export const syncPull = ( items ) => apiFetch( { path: `${ BASE }/sync/pull`, method: 'POST', data: { items } } );
export const syncMerge = ( kind, key, choices ) => apiFetch( { path: `${ BASE }/sync/merge`, method: 'POST', data: { kind, key, choices } } );
export const syncExport = ( items, format ) => apiFetch( { path: `${ BASE }/sync/export`, method: 'POST', data: { items, format } } );

export const switchScan = ( source = '' ) => apiFetch( { path: source ? `${ BASE }/switch?source=${ encodeURIComponent( source ) }` : `${ BASE }/switch` } );
export const switchImport = ( picked, source = '' ) =>
	apiFetch( { path: `${ BASE }/switch/import`, method: 'POST', data: source ? { source, picked } : picked } );
export const switchConvert = ( run ) => apiFetch( { path: `${ BASE }/switch/runs/${ run }/convert`, method: 'POST' } );
export const switchUndo = ( run ) => apiFetch( { path: `${ BASE }/switch/runs/${ run }/undo`, method: 'POST' } );
export const switchForget = ( run ) => apiFetch( { path: `${ BASE }/switch/runs/${ run }/forget`, method: 'POST' } );
