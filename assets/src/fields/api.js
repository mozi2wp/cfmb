/**
 * REST calls made from edit screens.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const BASE = '/cfmb/v1';

/**
 * Posts, terms or users a relational field may point at.
 *
 * @param {Object} args { group, field, search, page, post_type, all }.
 * @return {Promise<{items: Array, more: boolean}>} Results.
 */
export const search = ( args ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/search`, args ) } );

/**
 * Stored rows of a paged Repeater or Stacks field.
 *
 * @param {Object} args { object, group, field, indexes }.
 * @return {Promise<Array>} Rows in editor form.
 */
export const loadRows = ( args ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/rows`, args ) } );

const iconCache = new Map();

/**
 * Every icon of a library, fetched once per page load.
 *
 * @param {string} library Library ID.
 * @return {Promise<Array>} Icons.
 */
export function loadIcons( library ) {
	if ( ! iconCache.has( library ) ) {
		const request = apiFetch( {
			path: addQueryArgs( `${ BASE }/icons`, { library } ),
		} ).catch( ( error ) => {
			iconCache.delete( library );
			throw error;
		} );
		iconCache.set( library, request );
	}
	return iconCache.get( library );
}

/**
 * Embed preview through WordPress's own oEmbed proxy.
 *
 * @param {string} url URL.
 * @return {Promise<Object>} oEmbed response.
 */
export const embedPreview = ( url ) =>
	apiFetch( { path: addQueryArgs( '/oembed/1.0/proxy', { url } ) } );
