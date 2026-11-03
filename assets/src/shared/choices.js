/**
 * Choice lists: [{ value, label }] <-> "value : Label" lines.
 * Mirrors src/Fields/Choices.php.
 */

export function parseChoiceLines( text ) {
	const seen = new Set();

	return String( text || '' )
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => {
			const at = line.indexOf( ' : ' );
			if ( at === -1 ) {
				return { value: line, label: line };
			}
			const value = line.slice( 0, at ).trim();
			const label = line.slice( at + 3 ).trim();
			return { value, label: label || value };
		} )
		.filter( ( choice ) => {
			if ( ! choice.value || seen.has( choice.value ) ) {
				return false;
			}
			seen.add( choice.value );
			return true;
		} );
}

export function formatChoiceLines( choices ) {
	return ( Array.isArray( choices ) ? choices : [] )
		.map( ( { value, label } ) => ( value === label ? value : `${ value } : ${ label }` ) )
		.join( '\n' );
}
