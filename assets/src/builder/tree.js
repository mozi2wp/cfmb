/**
 * Field lists inside a group form a tree: Group and Repeater fields hold a
 * list, Stacks fields hold one list per layout. Each list has an id:
 * ''                    the group's own fields
 * 'fld_abc'             sub-fields of a Group or Repeater
 * 'fld_abc:lay_def'     fields of one Stacks layout
 */
import { __, sprintf } from '@wordpress/i18n';

export const STRUCTURE_TYPES = new Set( [ 'group', 'repeater' ] );

export const holdsFields = ( field ) => STRUCTURE_TYPES.has( field.type );

/**
 * Apply a change to one list, wherever it is.
 *
 * @param {Array}    fields Top-level fields.
 * @param {string}   listId List id.
 * @param {Function} change Receives the list, returns the new list.
 * @return {Array} New top-level fields.
 */
export function updateList( fields, listId, change ) {
	if ( listId === '' ) {
		return change( fields );
	}

	const [ fieldKey, layoutKey ] = listId.split( ':' );

	return fields.map( ( field ) => {
		if ( field.key === fieldKey ) {
			if ( layoutKey ) {
				return {
					...field,
					layouts: ( field.layouts || [] ).map( ( layout ) =>
						layout.key === layoutKey
							? {
									...layout,
									fields: change( layout.fields || [] ),
							  }
							: layout
					),
				};
			}
			return { ...field, fields: change( field.fields || [] ) };
		}

		let next = field;
		if ( field.fields?.length ) {
			const inner = updateList( field.fields, listId, change );
			if ( inner !== field.fields ) {
				next = { ...next, fields: inner };
			}
		}
		if ( field.layouts?.length ) {
			const layouts = field.layouts.map( ( layout ) => {
				const inner = updateList( layout.fields || [], listId, change );
				return inner !== layout.fields
					? { ...layout, fields: inner }
					: layout;
			} );
			if (
				layouts.some(
					( layout, index ) => layout !== field.layouts[ index ]
				)
			) {
				next = { ...next, layouts };
			}
		}
		return next;
	} );
}

/**
 * Find a field and the list holding it.
 *
 * @param {Array}  fields Top-level fields.
 * @param {string} key    Field key.
 * @param {string} listId Id of the list being searched.
 * @return {{field: Object, listId: string, index: number}|null} Location.
 */
export function locate( fields, key, listId = '' ) {
	for ( let index = 0; index < fields.length; index++ ) {
		const field = fields[ index ];
		if ( field.key === key ) {
			return { field, listId, index };
		}
		const inner = field.fields?.length
			? locate( field.fields, key, field.key )
			: null;
		if ( inner ) {
			return inner;
		}
		for ( const layout of field.layouts || [] ) {
			const found = locate(
				layout.fields || [],
				key,
				`${ field.key }:${ layout.key }`
			);
			if ( found ) {
				return found;
			}
		}
	}
	return null;
}

/**
 * Keys of the fields that contain a field, outermost first.
 *
 * @param {Array}  fields Top-level fields.
 * @param {string} key    Field key.
 * @return {string[]} Ancestor keys.
 */
export function ancestors( fields, key ) {
	const found = locate( fields, key );
	if ( ! found || found.listId === '' ) {
		return [];
	}
	const parentKey = found.listId.split( ':' )[ 0 ];
	return [ ...ancestors( fields, parentKey ), parentKey ];
}

/**
 * Whether a list sits inside a field (or is one of its own lists).
 *
 * @param {Object} field  Field.
 * @param {string} listId List id.
 * @return {boolean} Inside.
 */
export function listInside( field, listId ) {
	if ( listId === field.key || listId.startsWith( `${ field.key }:` ) ) {
		return true;
	}
	const children = [
		...( field.fields || [] ),
		...( field.layouts || [] ).flatMap( ( layout ) => layout.fields || [] ),
	];
	return children.some( ( child ) => listInside( child, listId ) );
}

/**
 * Move a field to another list.
 *
 * @param {Array}  fields Top-level fields.
 * @param {string} key    Field key.
 * @param {string} listId Target list id.
 * @param {number} index  Position in the target list; past the end appends.
 * @return {Array} New top-level fields.
 */
export function moveField( fields, key, listId, index ) {
	const found = locate( fields, key );
	if ( ! found || listInside( found.field, listId ) ) {
		return fields;
	}
	const without = updateList( fields, found.listId, ( list ) =>
		list.filter( ( item ) => item.key !== key )
	);
	return updateList( without, listId, ( list ) => {
		const next = [ ...list ];
		next.splice( Math.min( index, next.length ), 0, found.field );
		return next;
	} );
}

/**
 * Every list a field could move to, with readable names.
 *
 * @param {Array}  fields Top-level fields.
 * @param {string} prefix Label prefix for nested lists.
 * @return {Array<{id: string, label: string}>} Lists.
 */
export function allLists( fields, prefix = '' ) {
	const lists = prefix
		? []
		: [
				{
					id: '',
					label: __(
						'Top level of this group',
						'custom-fields-meta-box'
					),
				},
		  ];

	fields.forEach( ( field ) => {
		const name =
			field.label ||
			field.name ||
			__( '(no label)', 'custom-fields-meta-box' );
		if ( holdsFields( field ) ) {
			const label = prefix ? `${ prefix } › ${ name }` : name;
			/* translators: %s: Group or Repeater field label */
			lists.push( {
				id: field.key,
				label: sprintf(
					/* translators: %s: label */ __( 'Inside %s', 'custom-fields-meta-box' ),
					label
				),
			} );
			lists.push( ...allLists( field.fields || [], label ) );
		}
		if ( field.type === 'stacks' ) {
			( field.layouts || [] ).forEach( ( layout ) => {
				const label = `${ prefix ? `${ prefix } › ` : '' }${ name } › ${
					layout.label || layout.name
				}`;
				/* translators: %s: Stacks field and layout label */
				lists.push( {
					id: `${ field.key }:${ layout.key }`,
					label: sprintf(
						/* translators: %s: label */ __( 'Inside %s', 'custom-fields-meta-box' ),
						label
					),
				} );
				lists.push( ...allLists( layout.fields || [], label ) );
			} );
		}
	} );

	return lists;
}

/**
 * Field key -> name, at every depth, for spotting renames.
 *
 * @param {Array} fields Fields.
 * @return {Object} Names by key.
 */
export function namesByKey( fields ) {
	const names = {};
	( fields || [] ).forEach( ( field ) => {
		if ( field.name ) {
			names[ field.key ] = field.name;
		}
		Object.assign( names, namesByKey( field.fields ) );
		( field.layouts || [] ).forEach( ( layout ) =>
			Object.assign( names, namesByKey( layout.fields ) )
		);
	} );
	return names;
}

// Longest suffix a type adds to its name: "_address" for a map, "_target" for a link.
const PART_SUFFIX = { map: 8, link: 7 };

/**
 * Longest storage key a field can produce, as Group_Sanitizer counts it.
 *
 * @param {number} prefix Length of the storage prefix of the list it sits in.
 * @param {Object} field  Field.
 * @return {number} Length.
 */
export function keyLength( prefix, field ) {
	return (
		prefix +
		( field.name || '' ).length +
		( PART_SUFFIX[ field.type ] || 0 )
	);
}

/**
 * Storage prefix length for a field's own sub-fields: hero_ or items_1234_.
 *
 * @param {number} prefix Length of the field's own prefix.
 * @param {Object} field  Group, Repeater or Stacks field.
 * @return {number} Length.
 */
export function childPrefix( prefix, field ) {
	const own = prefix + ( field.name || '' ).length;
	if ( field.type === 'group' ) {
		return own + 1;
	}
	const max = Number( field.settings?.max ) || 0;
	return own + 1 + ( max > 0 ? String( max - 1 ).length : 4 ) + 1;
}
