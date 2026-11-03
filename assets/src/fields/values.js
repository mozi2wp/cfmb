/**
 * Shapes of field values on the edit screen.
 *
 * The server sends editor values (sub-values keyed by field key). Here they
 * are prepared for the UI, and serialized back to what Form_Saver expects.
 *
 * Repeater and Stacks values in the UI are always
 *   { paged, perPage, rows: [ { _uid, _from?, _layout?, _unloaded?, [key]: value } ] }
 * where _uid is a local id for React, _from the stored index the row came
 * from, and _unloaded marks rows of a paged field that were never fetched.
 */

export const STRUCTURES = { group: 'group', repeater: 'rows', stacks: 'stacks' };

const LAYOUT_ONLY = new Set( [ 'tab', 'message', 'accordion', 'preset' ] );

let uidCounter = 0;
export const newUid = () => `r${ Date.now().toString( 36 ) }${ ( uidCounter++ ).toString( 36 ) }`;

export const storesValue = ( field ) => ! LAYOUT_ONLY.has( field.type );

export const structureOf = ( field ) => STRUCTURES[ field.type ] || '';

/**
 * Sub-fields of a Group or Repeater row, or of a Stacks row's layout.
 *
 * @param {Object} field  Structure field.
 * @param {string} layout Layout name, for Stacks.
 * @return {Array} Field definitions.
 */
export function rowFields( field, layout = '' ) {
	if ( field.type === 'stacks' ) {
		return ( field.layouts || [] ).find( ( item ) => item.name === layout )?.fields || [];
	}
	return field.fields || [];
}

/**
 * Prepare one row from the server: add a local id and prepare nested values.
 *
 * @param {Object} field Repeater or Stacks field.
 * @param {Object} row   Row as sent by the server.
 * @return {Object} Row for the UI.
 */
export function prepareRow( field, row ) {
	const next = { ...row, _uid: row._uid || newUid() };
	rowFields( field, row._layout ).forEach( ( sub ) => {
		if ( structureOf( sub ) ) {
			next[ sub.key ] = prepare( sub, row[ sub.key ] );
		}
	} );
	return next;
}

/**
 * Prepare an editor value from the server for the UI.
 *
 * @param {Object} field Field definition.
 * @param {*}      value Editor value.
 * @return {*} UI value.
 */
export function prepare( field, value ) {
	const structure = structureOf( field );

	if ( ! structure ) {
		return value;
	}

	if ( structure === 'group' ) {
		const set = { ...( value && typeof value === 'object' && ! Array.isArray( value ) ? value : {} ) };
		( field.fields || [] ).forEach( ( sub ) => {
			if ( structureOf( sub ) ) {
				set[ sub.key ] = prepare( sub, set[ sub.key ] );
			}
		} );
		return set;
	}

	if ( value && value.paged ) {
		const loaded = new Map( ( value.rows || [] ).map( ( row ) => [ row._from, row ] ) );
		const rows = [];
		for ( let index = 0; index < ( value.total || 0 ); index++ ) {
			rows.push(
				loaded.has( index )
					? prepareRow( field, loaded.get( index ) )
					: { _uid: newUid(), _from: index, _layout: value.layouts?.[ index ], _unloaded: true }
			);
		}
		return { paged: true, perPage: value.perPage || 20, rows };
	}

	if ( value && Array.isArray( value.rows ) ) {
		return value;
	}

	return { paged: false, perPage: 0, rows: ( Array.isArray( value ) ? value : [] ).map( ( row ) => prepareRow( field, row ) ) };
}

/**
 * Prepare every value of a group.
 *
 * @param {Array}  fields Field definitions.
 * @param {Object} values Editor values by key.
 * @return {Object} UI values by key.
 */
export function prepareAll( fields, values ) {
	const out = { ...( values || {} ) };
	fields.forEach( ( field ) => {
		if ( structureOf( field ) ) {
			out[ field.key ] = prepare( field, out[ field.key ] );
		}
	} );
	return out;
}

/**
 * A new, empty row with sub-field defaults.
 *
 * @param {Object} field  Repeater or Stacks field.
 * @param {string} layout Layout name, for Stacks.
 * @return {Object} Row.
 */
export function newRow( field, layout = '' ) {
	const row = { _uid: newUid() };
	if ( field.type === 'stacks' ) {
		row._layout = layout;
	}
	rowFields( field, layout ).forEach( ( sub ) => {
		if ( ! storesValue( sub ) ) {
			return;
		}
		row[ sub.key ] = emptyValue( sub );
	} );
	return row;
}

/**
 * Starting value for a sub-field in a new row.
 *
 * @param {Object} field Field definition.
 * @return {*} Value.
 */
export function emptyValue( field ) {
	const structure = structureOf( field );
	if ( structure === 'group' ) {
		const set = {};
		( field.fields || [] ).forEach( ( sub ) => {
			if ( storesValue( sub ) ) {
				set[ sub.key ] = emptyValue( sub );
			}
		} );
		return set;
	}
	if ( structure ) {
		return { paged: false, perPage: 0, rows: [] };
	}
	const settings = field.settings || {};
	switch ( field.type ) {
		case 'toggle':
			return settings.default_value ? '1' : '0';
		case 'checkbox':
			return String( settings.default_value || '' )
				.split( /\r\n|\r|\n/ )
				.map( ( item ) => item.trim() )
				.filter( Boolean );
		case 'gallery':
		case 'relationship':
			return [];
		case 'select':
			if ( settings.multiple ) {
				return String( settings.default_value || '' )
					.split( /\r\n|\r|\n/ )
					.map( ( item ) => item.trim() )
					.filter( Boolean );
			}
			return settings.default_value ?? '';
		case 'post':
		case 'page_link':
		case 'user':
			return settings.multiple ? [] : null;
		case 'taxonomy':
			return [ 'checkbox', 'multi_select' ].includes( settings.appearance || 'checkbox' ) ? [] : null;
		case 'link':
			return { url: '', title: '', target: '' };
		case 'map':
			return { lat: '', lng: '', address: '', zoom: '' };
		case 'image':
		case 'file':
		case 'icon':
			return null;
		default:
			return settings.default_value ?? '';
	}
}

/**
 * Deep copy of a row with fresh local ids and no stored origin, for
 * duplicating and pasting.
 *
 * @param {Object} field Repeater or Stacks field.
 * @param {Object} row   Row.
 * @return {Object} Copy.
 */
export function cloneRow( field, row ) {
	const copy = JSON.parse( JSON.stringify( row ) );
	delete copy._from;
	delete copy._unloaded;
	copy._uid = newUid();
	rowFields( field, row._layout ).forEach( ( sub ) => {
		if ( structureOf( sub ) ) {
			copy[ sub.key ] = cloneValue( sub, copy[ sub.key ] );
		}
	} );
	return copy;
}

function cloneValue( field, value ) {
	if ( structureOf( field ) === 'group' ) {
		const set = { ...( value || {} ) };
		( field.fields || [] ).forEach( ( sub ) => {
			if ( structureOf( sub ) ) {
				set[ sub.key ] = cloneValue( sub, set[ sub.key ] );
			}
		} );
		return set;
	}
	return { paged: false, perPage: 0, rows: ( value?.rows || [] ).filter( ( row ) => ! row._unloaded ).map( ( row ) => cloneRow( field, row ) ) };
}

const idOf = ( item ) => ( item && typeof item === 'object' ? item.id : item );

/**
 * Value as the server expects it.
 *
 * @param {Object} field Field definition.
 * @param {*}      value UI value.
 * @return {*} Submitted value.
 */
export function serialize( field, value ) {
	const structure = structureOf( field );

	if ( structure === 'group' ) {
		const set = {};
		( field.fields || [] ).forEach( ( sub ) => {
			if ( storesValue( sub ) ) {
				set[ sub.key ] = serialize( sub, value?.[ sub.key ] );
			}
		} );
		return set;
	}

	if ( structure ) {
		const rows = ( value?.rows || [] ).map( ( row ) => {
			if ( row._unloaded ) {
				return { _keep: 1, _from: row._from };
			}
			const out = {};
			if ( field.type === 'stacks' ) {
				out._layout = row._layout;
			}
			// Where the row was stored; the server uses it to place error messages.
			if ( Number.isInteger( row._from ) ) {
				out._from = row._from;
			}
			rowFields( field, row._layout ).forEach( ( sub ) => {
				if ( storesValue( sub ) ) {
					out[ sub.key ] = serialize( sub, row[ sub.key ] );
				}
			} );
			return out;
		} );
		return value?.paged ? { paged: 1, rows } : rows;
	}

	switch ( field.type ) {
		case 'image':
		case 'file':
			return value && typeof value === 'object' ? value.id : value ?? '';
		case 'gallery':
			return ( Array.isArray( value ) ? value : [] ).map( idOf );
		case 'post':
		case 'page_link':
		case 'relationship':
		case 'user':
		case 'taxonomy':
			if ( Array.isArray( value ) ) {
				return value.map( idOf );
			}
			return value ? idOf( value ) : '';
		case 'icon':
			return value && typeof value === 'object' ? value.value : value ?? '';
		default:
			return value;
	}
}

/**
 * Serialize every value of a group.
 *
 * @param {Array}  fields Field definitions.
 * @param {Object} values UI values by key.
 * @return {Object} Submitted values by key.
 */
export function serializeAll( fields, values ) {
	const out = {};
	fields.forEach( ( field ) => {
		if ( storesValue( field ) && field.key in values ) {
			out[ field.key ] = serialize( field, values[ field.key ] );
		}
	} );
	return out;
}

/**
 * Shape of a Repeater or Stacks field by names and types, ignoring keys.
 * Two fields with the same signature can take each other's rows.
 *
 * @param {Object} field Field definition.
 * @return {string} Signature.
 */
export function fieldSignature( field ) {
	const shape = ( fields ) =>
		( fields || [] ).filter( storesValue ).map( ( sub ) => {
			const item = [ sub.name, sub.type ];
			if ( sub.fields ) {
				item.push( shape( sub.fields ) );
			}
			if ( sub.layouts ) {
				item.push( ( sub.layouts || [] ).map( ( layout ) => [ layout.name, shape( layout.fields ) ] ) );
			}
			return item;
		} );
	return JSON.stringify( [ field.type, field.type === 'stacks' ? ( field.layouts || [] ).map( ( layout ) => [ layout.name, shape( layout.fields ) ] ) : shape( field.fields ) ] );
}

function remapValue( from, to, value ) {
	const structure = structureOf( to );
	if ( structure === 'group' ) {
		return remapSet( from.fields || [], to.fields || [], value || {} );
	}
	if ( structure ) {
		return { paged: false, perPage: 0, rows: ( value?.rows || [] ).map( ( row ) => remapRow( from, to, row ) ) };
	}
	return value;
}

function remapSet( fromFields, toFields, values ) {
	const out = {};
	toFields.filter( storesValue ).forEach( ( sub ) => {
		const source = fromFields.find( ( item ) => item.name === sub.name );
		out[ sub.key ] = source ? remapValue( source, sub, values[ source.key ] ) : emptyValue( sub );
	} );
	return out;
}

/**
 * A row copied from one field, re-keyed for another field of the same shape.
 *
 * @param {Object} from Field the row came from.
 * @param {Object} to   Field the row goes into.
 * @param {Object} row  Row.
 * @return {Object} Row for the target field, with a fresh local id.
 */
export function remapRow( from, to, row ) {
	const next = { _uid: newUid(), ...remapSet( rowFields( from, row._layout ), rowFields( to, row._layout ), row ) };
	if ( to.type === 'stacks' ) {
		next._layout = row._layout;
	}
	return next;
}
