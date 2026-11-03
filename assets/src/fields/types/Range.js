export default function Range( { field, id, value, onChange, describedBy } ) {
	const settings = field.settings || {};
	const min =
		settings.min === '' || settings.min === undefined
			? 0
			: Number( settings.min );
	const max =
		settings.max === '' || settings.max === undefined
			? 100
			: Number( settings.max );
	const step = Number( settings.step ) || 1;
	const current =
		value === '' || value === null || value === undefined
			? min
			: Number( value );

	return (
		<div className="cfmb-range">
			{ settings.prepend && (
				<span className="cfmb-range__addon">{ settings.prepend }</span>
			) }
			<input
				id={ id }
				className="cfmb-range__slider"
				type="range"
				min={ min }
				max={ max }
				step={ step }
				value={ current }
				aria-describedby={ describedBy }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
			<input
				className="cfmb-input cfmb-range__number"
				type="number"
				min={ min }
				max={ max }
				step={ step }
				value={ value ?? '' }
				aria-label={ field.label }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
			{ settings.append && (
				<span className="cfmb-range__addon">{ settings.append }</span>
			) }
		</div>
	);
}
