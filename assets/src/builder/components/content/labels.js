/**
 * Fill a label template the way Content_Schema::labels() does on the server.
 *
 * @param {string} template Template with %1$s singular, %2$s plural, %3$s and %4$s lowercase.
 * @param {string} singular Singular name.
 * @param {string} plural   Plural name.
 * @return {string} Label.
 */
export function fillLabel( template, singular, plural ) {
	const values = [
		singular,
		plural,
		singular.toLowerCase(),
		plural.toLowerCase(),
	];

	return String( template || '' )
		.replace(
			/%(\d)\$s/g,
			( match, index ) => values[ Number( index ) - 1 ] ?? ''
		)
		.replace( /&larr;/g, '←' );
}

/**
 * Suggested key from a name: lowercase, underscores, trimmed to a length.
 *
 * @param {string} text Name.
 * @param {number} max  Maximum length.
 * @return {string} Key.
 */
export function suggestName( text, max ) {
	return String( text || '' )
		.trim()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9_-]+/g, '_' )
		.replace( /^[_-]+|[_-]+$/g, '' )
		.slice( 0, max );
}
