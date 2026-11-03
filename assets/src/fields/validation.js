/**
 * Client-side checks. The server repeats every check on save; these only
 * give faster feedback and stop the block editor from publishing too early.
 */
import { format as formatDate, getSettings } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import { isVisible } from '../shared/conditions';
import { rowFields, storesValue, structureOf } from './values';

export function isEmpty( field, value ) {
	const structure = structureOf( field );
	if ( structure === 'group' ) {
		return Object.keys( value || {} ).every( ( key ) => {
			const sub = ( field.fields || [] ).find(
				( item ) => item.key === key
			);
			return ! sub || isEmpty( sub, value[ key ] );
		} );
	}
	if ( structure ) {
		return ! value?.rows?.length;
	}
	switch ( field.type ) {
		case 'toggle':
			return value !== '1';
		case 'link':
			return ! value || ( ! value.url && ! value.title );
		case 'map':
			return ! value || ( value.lat === '' && ! value.address );
	}
	if ( Array.isArray( value ) ) {
		return value.length === 0;
	}
	return value === null || value === undefined || value === '';
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
const RGBA =
	/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i;

const count = ( settingValue ) => Number( settingValue ) || 0;

function rangeError( items, settings, few, many ) {
	const min = count( settings.min );
	const max = count( settings.max );
	if ( min > 0 && items < min ) {
		return sprintf( few, min );
	}
	if ( max > 0 && items > max ) {
		return sprintf( many, max );
	}
	return null;
}

function typeError( field, value, enforceRequired ) {
	const settings = field.settings || {};

	switch ( field.type ) {
		case 'text':
		case 'textarea': {
			const limit = Number( settings.maxlength ) || 0;
			if ( limit > 0 && String( value ).length > limit ) {
				return sprintf(
					/* translators: %d: maximum number of characters */
					_n(
						'Use %d character or fewer.',
						'Use %d characters or fewer.',
						limit,
						'custom-fields-meta-box'
					),
					limit
				);
			}
			return null;
		}
		case 'email':
			return EMAIL.test( String( value ).trim() )
				? null
				: __(
						'Enter an email address, like name@example.com.',
						'custom-fields-meta-box'
				  );
		case 'url':
		case 'oembed':
			try {
				// eslint-disable-next-line no-new
				new URL( String( value ).trim() );
				return null;
			} catch ( e ) {
				return __(
					'Enter a full web address, like https://example.com.',
					'custom-fields-meta-box'
				);
			}
		case 'number':
		case 'range': {
			const number = Number( value );
			if (
				String( value ).trim() === '' ||
				! Number.isFinite( number )
			) {
				return __( 'Enter a number.', 'custom-fields-meta-box' );
			}
			if (
				settings.min !== '' &&
				settings.min !== undefined &&
				number < Number( settings.min )
			) {
				/* translators: %s: minimum allowed number */
				return sprintf(
					/* translators: %s: min */ __(
						'Enter a number of %s or more.',
						'custom-fields-meta-box'
					),
					settings.min
				);
			}
			if (
				settings.max !== '' &&
				settings.max !== undefined &&
				number > Number( settings.max )
			) {
				/* translators: %s: maximum allowed number */
				return sprintf(
					/* translators: %s: max */ __(
						'Enter a number of %s or less.',
						'custom-fields-meta-box'
					),
					settings.max
				);
			}
			return null;
		}
		case 'color': {
			const text = String( value ).trim();
			return HEX.test( text ) || ( settings.alpha && RGBA.test( text ) )
				? null
				: __(
						'Enter a color as a hex code, like #1e90ff.',
						'custom-fields-meta-box'
				  );
		}
		case 'date':
		case 'datetime': {
			const { min = '', max = '' } = settings.calendar || {};
			const format = getSettings()?.formats?.date || 'F j, Y';
			if ( min && String( value ) < min ) {
				/* translators: %s: earliest allowed date */
				return sprintf(
					/* translators: %s: value */ __(
						'Choose a date on or after %s.',
						'custom-fields-meta-box'
					),
					formatDate( format, min )
				);
			}
			if ( max && String( value ) > max ) {
				/* translators: %s: latest allowed date */
				return sprintf(
					/* translators: %s: value */ __(
						'Choose a date on or before %s.',
						'custom-fields-meta-box'
					),
					formatDate( format, max )
				);
			}
			return null;
		}
		case 'link':
			return ! value.url && value.title
				? __(
						'Add the address the link points to.',
						'custom-fields-meta-box'
				  )
				: null;
		case 'map': {
			const hasLat = value.lat !== '' && value.lat !== undefined;
			const hasLng = value.lng !== '' && value.lng !== undefined;
			if ( hasLat !== hasLng ) {
				return __(
					'Enter both latitude and longitude, or pick the place on the map.',
					'custom-fields-meta-box'
				);
			}
			if (
				hasLat &&
				( Math.abs( Number( value.lat ) ) > 90 ||
					Math.abs( Number( value.lng ) ) > 180 ||
					! Number.isFinite( Number( value.lat ) ) ||
					! Number.isFinite( Number( value.lng ) ) )
			) {
				return __(
					'Latitude must be between -90 and 90, longitude between -180 and 180.',
					'custom-fields-meta-box'
				);
			}
			return null;
		}
		case 'gallery':
			return rangeError(
				value.length,
				settings,
				/* translators: %d: minimum number of images */
				_n(
					'Add at least %d image.',
					'Add at least %d images.',
					count( settings.min ),
					'custom-fields-meta-box'
				),
				/* translators: %d: maximum number of images */
				_n(
					'Use %d image or fewer.',
					'Use %d images or fewer.',
					count( settings.max ),
					'custom-fields-meta-box'
				)
			);
		case 'relationship':
			return rangeError(
				value.length,
				settings,
				/* translators: %d: minimum number of posts */
				_n(
					'Choose at least %d post.',
					'Choose at least %d posts.',
					count( settings.min ),
					'custom-fields-meta-box'
				),
				/* translators: %d: maximum number of posts */
				_n(
					'Choose %d post or fewer.',
					'Choose %d posts or fewer.',
					count( settings.max ),
					'custom-fields-meta-box'
				)
			);
		case 'repeater':
		case 'stacks':
			return rowsError( field, value, enforceRequired );
		default:
			return null;
	}
}

function rowsError( field, value, enforceRequired ) {
	const rows = value?.rows || [];
	const settings = field.settings || {};
	const min = count( settings.min );
	const max = count( settings.max );

	if (
		min > 0 &&
		rows.length < min &&
		( rows.length > 0 || enforceRequired )
	) {
		/* translators: %d: minimum number of rows */
		return sprintf(
			/* translators: %d: min */ _n(
				'Add at least %d row.',
				'Add at least %d rows.',
				min,
				'custom-fields-meta-box'
			),
			min
		);
	}
	if ( max > 0 && rows.length > max ) {
		/* translators: %d: maximum number of rows */
		return sprintf(
			/* translators: %d: max */ _n(
				'Use %d row or fewer.',
				'Use %d rows or fewer.',
				max,
				'custom-fields-meta-box'
			),
			max
		);
	}

	if ( field.type !== 'stacks' ) {
		return null;
	}

	for ( const layout of field.layouts || [] ) {
		const used = rows.filter(
			( row ) => row._layout === layout.name
		).length;
		if (
			count( layout.min ) > 0 &&
			used < count( layout.min ) &&
			enforceRequired
		) {
			return sprintf(
				/* translators: 1: number of rows, 2: layout label */
				_n(
					'Add at least %1$d "%2$s" row.',
					'Add at least %1$d "%2$s" rows.',
					count( layout.min ),
					'custom-fields-meta-box'
				),
				count( layout.min ),
				layout.label
			);
		}
		if ( count( layout.max ) > 0 && used > count( layout.max ) ) {
			return sprintf(
				/* translators: 1: number of rows, 2: layout label */
				_n(
					'Use %1$d "%2$s" row or fewer.',
					'Use %1$d "%2$s" rows or fewer.',
					count( layout.max ),
					'custom-fields-meta-box'
				),
				count( layout.max ),
				layout.label
			);
		}
	}
	return null;
}

/**
 * Error message for a field's own value, or null. Sub-fields are checked by
 * validateTree().
 *
 * @param {Object}  field           Field definition.
 * @param {*}       value           Current value.
 * @param {boolean} enforceRequired Whether to check "required".
 * @return {string|null} Message.
 */
export function validateField( field, value, enforceRequired ) {
	if ( isEmpty( field, value ) ) {
		if ( enforceRequired && field.required ) {
			return __( 'This field is required.', 'custom-fields-meta-box' );
		}
		// Empty rows can still be below a minimum.
		return structureOf( field ) && structureOf( field ) !== 'group'
			? rowsError( field, value, enforceRequired )
			: null;
	}
	return typeError( field, value, enforceRequired );
}

/**
 * Every problem in a value and inside it, with the path the server uses:
 * "fld_a", "fld_a.2.fld_b".
 *
 * @param {Object}  field           Field definition.
 * @param {*}       value           Current value.
 * @param {boolean} enforceRequired Whether to check "required".
 * @param {string}  path            Path of this field.
 * @return {Array<{path: string, field: Object, message: string}>} Problems.
 */
export function validateTree( field, value, enforceRequired, path ) {
	const problems = [];
	const own = validateField( field, value, enforceRequired );
	if ( own ) {
		problems.push( { path, field, message: own } );
	}

	const structure = structureOf( field );
	if ( structure === 'group' ) {
		problems.push(
			...validateSet(
				field.fields || [],
				value || {},
				enforceRequired,
				path
			)
		);
	} else if ( structure ) {
		( value?.rows || [] ).forEach( ( row, index ) => {
			if ( ! row._unloaded ) {
				problems.push(
					...validateSet(
						rowFields( field, row._layout ),
						row,
						enforceRequired,
						`${ path }.${ index }`
					)
				);
			}
		} );
	}
	return problems;
}

/**
 * Problems in one set of sub-values (a Group or a row).
 *
 * @param {Array}   fields          Sub-field definitions.
 * @param {Object}  values          Values by key.
 * @param {boolean} enforceRequired Whether to check "required".
 * @param {string}  path            Path of the set.
 * @return {Array} Problems.
 */
export function validateSet( fields, values, enforceRequired, path ) {
	return fields.filter( storesValue ).flatMap( ( sub ) => {
		const visible = isVisible( sub, values );
		if ( ! visible ) {
			return [];
		}
		return validateTree(
			sub,
			values[ sub.key ],
			enforceRequired,
			`${ path }.${ sub.key }`
		);
	} );
}
