/**
 * Field conditional logic.
 *
 * Mirrors src/Location/Conditions.php. Both must give the same answer so a
 * field hidden in the browser is also treated as hidden on save.
 */

export const OPERATORS = [ '==', '!=', 'empty', 'not_empty', 'contains', '>', '<' ];

const isEmptyValue = ( value ) =>
	value === null ||
	value === undefined ||
	value === '' ||
	( Array.isArray( value ) && value.length === 0 );

const asText = ( value ) => {
	if ( typeof value === 'boolean' ) {
		return value ? '1' : '0';
	}
	if ( typeof value === 'number' ) {
		return String( value );
	}
	return typeof value === 'string' ? value : null;
};

const isNumeric = ( value ) =>
	( typeof value === 'number' && Number.isFinite( value ) ) ||
	( typeof value === 'string' && value.trim() !== '' && Number.isFinite( Number( value ) ) );

const equals = ( actual, expected ) => {
	if ( Array.isArray( actual ) ) {
		return actual.map( String ).includes( expected );
	}
	const text = asText( actual );
	return text !== null && text === expected;
};

/**
 * Compare one watched value against a rule.
 *
 * @param {*}      actual   Current value of the watched field.
 * @param {string} operator Operator.
 * @param {string} expected Value in the rule.
 * @return {boolean} Whether the rule passes.
 */
export function compare( actual, operator, expected ) {
	expected = String( expected ?? '' );

	switch ( operator ) {
		case 'empty':
			return isEmptyValue( actual );
		case 'not_empty':
			return ! isEmptyValue( actual );
		case '!=':
			return ! equals( actual, expected );
		case 'contains':
			if ( Array.isArray( actual ) ) {
				return actual.map( String ).includes( expected );
			}
			return (
				expected !== '' &&
				asText( actual ) !== null &&
				asText( actual ).includes( expected )
			);
		case '>':
		case '<':
			if ( ! isNumeric( actual ) || ! isNumeric( expected ) ) {
				return false;
			}
			return operator === '>'
				? Number( actual ) > Number( expected )
				: Number( actual ) < Number( expected );
		default:
			return equals( actual, expected );
	}
}

/**
 * Whether a field is visible given sibling values.
 *
 * @param {Object} field  Field definition with `conditions` (OR of AND rule sets).
 * @param {Object} values Values keyed by field key.
 * @return {boolean} Visibility.
 */
export function isVisible( field, values ) {
	const sets = Array.isArray( field.conditions ) ? field.conditions : [];

	if ( sets.length === 0 ) {
		return true;
	}

	return sets.some(
		( rules ) =>
			Array.isArray( rules ) &&
			rules.every( ( rule ) =>
				compare( comparable( values[ rule.field ] ), rule.operator, rule.value )
			)
	);
}

/**
 * Editor values can be objects (media, posts, icons, rows); conditions
 * compare what the server stores.
 *
 * @param {*} value Editor value.
 * @return {*} Value to compare.
 */
function comparable( value ) {
	if ( value && typeof value === 'object' && ! Array.isArray( value ) ) {
		if ( Array.isArray( value.rows ) ) {
			return value.rows;
		}
		if ( 'id' in value ) {
			return String( value.id );
		}
		if ( 'value' in value ) {
			return value.value;
		}
	}
	if ( Array.isArray( value ) ) {
		return value.map( ( item ) => ( item && typeof item === 'object' && 'id' in item ? String( item.id ) : item ) );
	}
	return value;
}
