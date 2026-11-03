/**
 * Selection helpers for the Switch Assistant, kept pure for unit tests.
 */

/**
 * Everything not imported yet starts picked.
 *
 * @param {Object} scan     Scan result.
 * @param {Array}  sections Sections with `list` and `pick` keys.
 * @return {Object} Picked keys by pick name.
 */
export function initialPicks( scan, sections ) {
	const picks = {};

	sections.forEach( ( section ) => {
		picks[ section.pick ] = ( scan[ section.list ] || [] )
			.filter( ( item ) => ! item.imported )
			.map( ( item ) => item.key );
	} );

	return picks;
}

/**
 * Add or remove one key.
 *
 * @param {Object}  picks   Picked keys.
 * @param {string}  pick    Pick name.
 * @param {string}  key     Item key.
 * @param {boolean} checked Whether it is picked now.
 * @return {Object} New picks.
 */
export function togglePick( picks, pick, key, checked ) {
	const current = picks[ pick ] || [];
	const next = checked
		? [ ...new Set( [ ...current, key ] ) ]
		: current.filter( ( item ) => item !== key );

	return { ...picks, [ pick ]: next };
}

/**
 * Call a batch step until the run reaches a status, reporting each result.
 *
 * @param {Function} step     Returns a promise of the run summary.
 * @param {string}   finished Status that ends the loop.
 * @param {Function} onStep   Receives each summary.
 * @param {number}   limit    Safety limit on calls.
 * @return {Promise<Object>} The last summary.
 */
export async function runAll( step, finished, onStep, limit = 10000 ) {
	let result = null;

	for ( let calls = 0; calls < limit; calls++ ) {
		result = await step();
		onStep( result );

		if ( result.status === finished ) {
			return result;
		}
	}

	throw new Error( 'Stopped after too many steps.' );
}

/**
 * Sections of a scan. ACF and SCF scans use fixed lists; other sources send
 * their own sections, which are spread onto the scan by ID.
 *
 * @param {Object} scan     Scan result.
 * @param {Array}  fallback Sections for ACF and SCF.
 * @return {{ scan: Object, sections: Array }} Scan with section lists, and the sections.
 */
export function scanSections( scan, fallback ) {
	if ( ! scan || ! Array.isArray( scan.sections ) ) {
		return { scan, sections: fallback };
	}

	const lists = {};
	const sections = scan.sections.map( ( section ) => {
		lists[ section.id ] = section.items || [];
		return { list: section.id, pick: section.id, title: section.title };
	} );

	return { scan: { ...scan, ...lists }, sections };
}
