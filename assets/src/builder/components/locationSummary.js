import { __, sprintf } from '@wordpress/i18n';
import { rulesByParam } from '../config';

/**
 * Short text for a group's location, like "Post type is post or Taxonomy is category".
 *
 * @param {Array} location Rule sets.
 * @return {string} Summary.
 */
export default function locationSummary( location ) {
	if ( ! Array.isArray( location ) || ! location.length ) {
		return __( 'Not shown anywhere', 'custom-fields-meta-box' );
	}

	const sets = location.map( ( rules ) =>
		rules
			.map( ( rule ) => {
				const type = rulesByParam[ rule.param ];
				const operator = type?.operators.find( ( item ) => item.value === rule.operator );
				return sprintf(
					/* translators: 1: rule name, 2: operator, 3: value */
					__( '%1$s %2$s %3$s', 'custom-fields-meta-box' ),
					type?.label || rule.param,
					operator?.label || rule.operator,
					rule.value
				);
			} )
			.join( __( ' and ', 'custom-fields-meta-box' ) )
	);

	return sets.join( __( ' or ', 'custom-fields-meta-box' ) );
}
