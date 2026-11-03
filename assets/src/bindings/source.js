/**
 * Pure helpers for the bindings source, kept apart so they can be unit tested.
 */

/**
 * Whether an object argument means the post the block shows.
 *
 * @param {string} object Object argument.
 * @return {boolean} Current post.
 */
export const isCurrentObject = ( object ) =>
	! object || object === 'post' || object === 'current';

/**
 * The part of a field value an attribute takes, matching the server's Source::pick().
 *
 * @param {Object|null|undefined} parts     Value parts from the REST route.
 * @param {string}                attribute Attribute name.
 * @return {*} Value, or undefined when there is nothing to show.
 */
export function pick( parts, attribute ) {
	if ( ! parts ) {
		return undefined;
	}
	switch ( attribute ) {
		case 'url':
		case 'href':
			return parts.url || undefined;
		case 'alt':
			return parts.alt;
		case 'title':
			return parts.title;
		case 'id':
			return parts.id || undefined;
		case 'content':
		case 'caption':
			return parts.html;
		default:
			return parts.text;
	}
}

/**
 * Fields the bindings panel offers: the post type's fields, then options pages.
 *
 * @param {Object} config   Bindings config from the server.
 * @param {string} postType Post type being edited.
 * @return {Array<{label: string, type: string, args: Object}>} Fields.
 */
export function fieldList( config, postType ) {
	const list = ( ( config.postTypes || {} )[ postType ] || [] ).map(
		( field ) => ( {
			label: field.label,
			type: 'string',
			args: { field: field.name },
		} )
	);

	( config.options || [] ).forEach( ( page ) => {
		( page.fields || [] ).forEach( ( field ) => {
			list.push( {
				label: `${ page.title }: ${ field.label }`,
				type: 'string',
				args: { field: field.name, object: page.object },
			} );
		} );
	} );

	return list;
}
