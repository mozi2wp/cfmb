/**
 * Turns field values on the edit screen into HTML for SEO analysis.
 */

const escape = ( text ) =>
	String( text ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );

const STRUCTURES = [ 'group', 'repeater', 'stacks' ];

function imageHtml( id, lookup ) {
	const details = id ? lookup( Number( id ) ) : null;
	if ( ! details || ( ! details.url && ! details.alt ) ) {
		return '';
	}
	return `<img src="${ escape( details.url ) }" alt="${ escape( details.alt ) }" />`;
}

/**
 * HTML for one value of an analysed field.
 *
 * @param {Object}   field  Field definition.
 * @param {*}        value  UI value.
 * @param {Function} lookup Attachment details by ID, or null while loading.
 * @return {string} HTML.
 */
export function valueHtml( field, value, lookup ) {
	if ( value === undefined || value === null || value === '' ) {
		return '';
	}

	switch ( field.type ) {
		case 'wysiwyg':
			return `${ String( value ) }\n`;
		case 'text':
		case 'textarea':
			return `<p>${ escape( value ) }</p>\n`;
		case 'image':
			return imageHtml( value, lookup );
		case 'gallery':
			return ( Array.isArray( value ) ? value : [] ).map( ( id ) => imageHtml( id, lookup ) ).join( '' );
		default:
			if ( typeof value === 'string' || typeof value === 'number' ) {
				return `<p>${ escape( value ) }</p>\n`;
			}
			return '';
	}
}

function subFields( field, row ) {
	if ( field.type === 'stacks' ) {
		return ( field.layouts || [] ).find( ( layout ) => layout.name === row?._layout )?.fields || [];
	}
	return field.fields || [];
}

/**
 * HTML for every analysed field in a list, at any depth.
 *
 * @param {Array}    fields Field definitions.
 * @param {Object}   values UI values by field key.
 * @param {Set}      wanted Keys of analysed fields.
 * @param {Function} lookup Attachment details by ID.
 * @return {string} HTML.
 */
export function contentFor( fields, values, wanted, lookup ) {
	let html = '';

	( fields || [] ).forEach( ( field ) => {
		const value = values?.[ field.key ];

		if ( ! STRUCTURES.includes( field.type ) ) {
			if ( wanted.has( field.key ) ) {
				html += valueHtml( field, value, lookup );
			}
			return;
		}

		if ( field.type === 'group' ) {
			html += contentFor( field.fields, value || {}, wanted, lookup );
			return;
		}

		( value?.rows || [] ).forEach( ( row ) => {
			html += contentFor( subFields( field, row ), row, wanted, lookup );
		} );
	} );

	return html;
}
