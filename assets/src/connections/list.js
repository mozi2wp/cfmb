/**
 * List helpers for Connection boxes, kept pure for unit tests.
 */

/**
 * Add an item, respecting a limit. With a limit of one the item replaces
 * the current one; with a larger limit nothing changes once full.
 *
 * @param {Array}  list Selected items.
 * @param {Object} item Item to add.
 * @param {number} max  Limit; 0 for none.
 * @return {Array} The new list, or the same list when nothing changed.
 */
export function toggleItem( list, item, max ) {
	if ( list.some( ( other ) => other.id === item.id ) ) {
		return list;
	}

	if ( max === 1 ) {
		return [ item ];
	}

	if ( max > 0 && list.length >= max ) {
		return list;
	}

	return [ ...list, item ];
}

/**
 * Move an item up or down.
 *
 * @param {Array}  list  Items.
 * @param {number} index Position of the item.
 * @param {number} step  -1 for up, 1 for down.
 * @return {Array} The new list.
 */
export function moveItem( list, index, step ) {
	const target = index + step;

	if ( target < 0 || target >= list.length ) {
		return list;
	}

	const next = [ ...list ];
	const [ item ] = next.splice( index, 1 );
	next.splice( target, 0, item );

	return next;
}
