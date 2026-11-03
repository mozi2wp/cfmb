export default function Message( { field } ) {
	return (
		<div
			className="cfmb-field cfmb-field--message"
			data-cfmb-field={ field.key }
		>
			{ field.label && (
				<p className="cfmb-field__label">{ field.label }</p>
			) }
			<div
				className="cfmb-message"
				// Run through wp_kses_post() when the group is saved.
				dangerouslySetInnerHTML={ {
					__html: field.settings?.message || '',
				} }
			/>
		</div>
	);
}
