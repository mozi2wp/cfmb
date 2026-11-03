/**
 * Stable keys, same format as src/Schema/Key_Generator.php.
 */

const randomHex = ( bytes ) => {
	const buffer = new Uint8Array( bytes );
	window.crypto.getRandomValues( buffer );
	return Array.from( buffer, ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) ).join( '' );
};

export const newFieldKey = () => `fld_${ randomHex( 5 ) }`;
export const newGroupKey = () => `grp_${ randomHex( 5 ) }`;

/**
 * Field name from free text. Mirrors Group_Sanitizer::sanitize_name(), and
 * also trims underscores at the ends because this is used to suggest a name.
 *
 * @param {string} text   Label or typed name.
 * @param {boolean} trim  Whether to trim underscores at the ends.
 * @return {string} Name.
 */
export function toFieldName( text, trim = false ) {
	const name = String( text || '' )
		.trim()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9_]+/g, '_' );

	if ( /^_*$/.test( name ) ) {
		return '';
	}

	return trim ? name.replace( /^_+|_+$/g, '' ) : name;
}

export const newLayoutKey = () => `lay_${ randomHex( 5 ) }`;
