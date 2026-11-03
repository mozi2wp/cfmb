export default function Textarea( { field, id, value, onChange, describedBy } ) {
	const settings = field.settings || {};

	return (
		<textarea
			id={ id }
			className="cfmb-input cfmb-input--textarea"
			rows={ Number( settings.rows ) || 4 }
			value={ value ?? '' }
			placeholder={ settings.placeholder || undefined }
			maxLength={ Number( settings.maxlength ) > 0 ? Number( settings.maxlength ) : undefined }
			aria-describedby={ describedBy }
			aria-required={ field.required || undefined }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	);
}
