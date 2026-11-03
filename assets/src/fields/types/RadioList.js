export default function RadioList( { field, id, value, onChange } ) {
	const settings = field.settings || {};
	const choices = settings.choices || [];

	return (
		<ul
			className={ `cfmb-choice-list cfmb-choice-list--${
				settings.layout || 'vertical'
			}` }
		>
			{ choices.map( ( choice, index ) => (
				<li key={ choice.value } className="cfmb-choice-list__item">
					<label>
						<input
							id={ index === 0 ? id : undefined }
							type="radio"
							name={ id }
							value={ choice.value }
							checked={ value === choice.value }
							onChange={ () => onChange( choice.value ) }
						/>
						{ choice.label }
					</label>
				</li>
			) ) }
		</ul>
	);
}
