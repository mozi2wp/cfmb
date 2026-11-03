import { __ } from '@wordpress/i18n';

export default function CheckboxList( { field, id, value, onChange } ) {
	const settings = field.settings || {};
	const choices = settings.choices || [];
	const selected = Array.isArray( value ) ? value : [];
	const allValues = choices.map( ( choice ) => choice.value );
	const allChecked = choices.length > 0 && allValues.every( ( item ) => selected.includes( item ) );

	// Keep choice order, whatever order boxes were ticked in.
	const toggle = ( item, checked ) =>
		onChange( allValues.filter( ( v ) => ( v === item ? checked : selected.includes( v ) ) ) );

	return (
		<ul className={ `cfmb-choice-list cfmb-choice-list--${ settings.layout || 'vertical' }` }>
			{ settings.toggle_all && choices.length > 1 && (
				<li className="cfmb-choice-list__item cfmb-choice-list__item--all">
					<label>
						<input
							type="checkbox"
							checked={ allChecked }
							onChange={ ( event ) => onChange( event.target.checked ? allValues : [] ) }
						/>
						{ __( 'Select all', 'custom-fields-meta-box' ) }
					</label>
				</li>
			) }
			{ choices.map( ( choice, index ) => (
				<li key={ choice.value } className="cfmb-choice-list__item">
					<label>
						<input
							id={ index === 0 ? id : undefined }
							type="checkbox"
							value={ choice.value }
							checked={ selected.includes( choice.value ) }
							onChange={ ( event ) => toggle( choice.value, event.target.checked ) }
						/>
						{ choice.label }
					</label>
				</li>
			) ) }
		</ul>
	);
}
