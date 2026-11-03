import { FormToggle } from '@wordpress/components';

export default function Toggle( { field, id, value, onChange, describedBy } ) {
	const message = field.settings?.message;

	return (
		<div className="cfmb-toggle">
			<FormToggle
				id={ id }
				checked={ value === '1' }
				aria-describedby={ describedBy }
				onChange={ ( event ) => onChange( event.target.checked ? '1' : '0' ) }
			/>
			{ message && (
				<label className="cfmb-toggle__message" htmlFor={ id }>
					{ message }
				</label>
			) }
		</div>
	);
}
