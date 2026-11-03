/**
 * Text, email, URL, password and number inputs.
 */
const INPUT_TYPES = {
	text: 'text',
	email: 'email',
	url: 'url',
	password: 'password',
	number: 'number',
};

export default function TextInput( { field, id, value, onChange, describedBy } ) {
	const settings = field.settings || {};
	const prepend = settings.prepend;
	const append = settings.append;

	const input = (
		<input
			id={ id }
			className="cfmb-input"
			type={ INPUT_TYPES[ field.type ] || 'text' }
			value={ value ?? '' }
			placeholder={ settings.placeholder || undefined }
			maxLength={ Number( settings.maxlength ) > 0 ? Number( settings.maxlength ) : undefined }
			min={ field.type === 'number' && settings.min !== '' ? settings.min : undefined }
			max={ field.type === 'number' && settings.max !== '' ? settings.max : undefined }
			step={ field.type === 'number' ? settings.step || 'any' : undefined }
			autoComplete={ field.type === 'password' ? 'new-password' : undefined }
			aria-describedby={ describedBy }
			aria-required={ field.required || undefined }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	);

	if ( ! prepend && ! append ) {
		return input;
	}

	return (
		<div className="cfmb-input-group">
			{ prepend && <span className="cfmb-input-group__addon">{ prepend }</span> }
			{ input }
			{ append && <span className="cfmb-input-group__addon">{ append }</span> }
		</div>
	);
}
