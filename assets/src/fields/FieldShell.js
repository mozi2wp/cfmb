/**
 * Label, instructions, required marker and error around a field control.
 */
import { __ } from '@wordpress/i18n';

export default function FieldShell( { field, inputId, path, error, labelAsLegend = false, children } ) {
	const width = Number( field.wrapper?.width ) || 0;
	const classes = [
		'cfmb-field',
		`cfmb-field--${ field.type }`,
		error ? 'has-error' : '',
		field.wrapper?.class || '',
	]
		.filter( Boolean )
		.join( ' ' );

	const helpId = field.instructions ? `${ inputId }__help` : undefined;
	const errorId = error ? `${ inputId }__error` : undefined;

	const label = (
		<>
			{ field.label }
			{ field.required && (
				<span className="cfmb-field__required" aria-hidden="true">
					{ ' *' }
				</span>
			) }
			{ field.required && (
				<span className="screen-reader-text">
					{ ' ' }
					{ __( '(required)', 'custom-fields-meta-box' ) }
				</span>
			) }
		</>
	);

	const describedBy = [ helpId, errorId ].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<div
			className={ classes }
			style={ width > 0 ? { '--cfmb-field-width': `${ width }%` } : undefined }
			data-cfmb-field={ field.key }
			data-cfmb-path={ path || field.key }
		>
			{ labelAsLegend ? (
				<fieldset className="cfmb-field__fieldset" aria-describedby={ describedBy }>
					<legend className="cfmb-field__label">{ label }</legend>
					<div className="cfmb-field__control">{ children( { describedBy } ) }</div>
				</fieldset>
			) : (
				<>
					{ field.label && (
						<label className="cfmb-field__label" htmlFor={ inputId }>
							{ label }
						</label>
					) }
					<div className="cfmb-field__control">{ children( { describedBy } ) }</div>
				</>
			) }
			{ field.instructions && (
				<p
					id={ helpId }
					className="cfmb-field__instructions"
					// Instructions are run through wp_kses_post() when the group is saved.
					dangerouslySetInnerHTML={ { __html: field.instructions } }
				/>
			) }
			{ error && (
				<p id={ errorId } className="cfmb-field__error" role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}
