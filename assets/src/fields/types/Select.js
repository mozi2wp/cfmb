import { __ } from '@wordpress/i18n';

export default function Select( { field, id, value, onChange, describedBy } ) {
	const settings = field.settings || {};
	const choices = settings.choices || [];

	if ( settings.multiple ) {
		const selected = Array.isArray( value ) ? value : [];

		return (
			<select
				id={ id }
				className="cfmb-input cfmb-select cfmb-select--multiple"
				multiple
				size={ Math.min( 8, Math.max( 3, choices.length ) ) }
				value={ selected }
				aria-describedby={ describedBy }
				aria-required={ field.required || undefined }
				onChange={ ( event ) =>
					onChange( Array.from( event.target.selectedOptions, ( option ) => option.value ) )
				}
			>
				{ choices.map( ( choice ) => (
					<option key={ choice.value } value={ choice.value }>
						{ choice.label }
					</option>
				) ) }
			</select>
		);
	}

	const showEmpty = settings.allow_null || value === '' || value === null || value === undefined;

	return (
		<select
			id={ id }
			className="cfmb-input cfmb-select"
			value={ value ?? '' }
			aria-describedby={ describedBy }
			aria-required={ field.required || undefined }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ showEmpty && <option value="">{ settings.placeholder || __( '— Select —', 'custom-fields-meta-box' ) }</option> }
			{ choices.map( ( choice ) => (
				<option key={ choice.value } value={ choice.value }>
					{ choice.label }
				</option>
			) ) }
		</select>
	);
}
