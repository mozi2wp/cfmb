/**
 * Group field: a set of sub-fields shown together.
 */
import Fields from '../Fields';

export default function GroupField( { field, value, onChange, path } ) {
	const display = field.settings?.display === 'plain' ? 'plain' : 'box';

	return (
		<div className={ `cfmb-subfields cfmb-subfields--${ display }` }>
			<Fields
				fields={ field.fields || [] }
				values={ value || {} }
				path={ path }
				onChange={ ( key, update ) =>
					onChange( ( current ) => {
						const set = current || {};
						return { ...set, [ key ]: typeof update === 'function' ? update( set[ key ] ) : update };
					} )
				}
			/>
		</div>
	);
}
