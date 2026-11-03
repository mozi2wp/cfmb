/**
 * Color field: a swatch that opens the WordPress color picker, plus a text box.
 */
import { ColorPicker, Dropdown } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export default function Color( { field, id, value, onChange, describedBy } ) {
	const color = typeof value === 'string' ? value : '';
	const alpha = !! field.settings?.alpha;

	return (
		<div className="cfmb-color">
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<button
						type="button"
						className="cfmb-color__swatch"
						aria-expanded={ isOpen }
						aria-label={
							color
								? sprintf(
										/* translators: %s: color code */ __(
											'Choose color, current %s',
											'custom-fields-meta-box'
										),
										color
								  )
								: __( 'Choose color', 'custom-fields-meta-box' )
						}
						onClick={ onToggle }
						style={ { '--cfmb-swatch': color || 'transparent' } }
					/>
				) }
				renderContent={ () => (
					<ColorPicker
						color={ color || '#ffffff' }
						enableAlpha={ alpha }
						onChange={ ( next ) => onChange( next ) }
					/>
				) }
			/>
			<input
				id={ id }
				className="cfmb-input cfmb-color__text"
				type="text"
				spellCheck={ false }
				placeholder="#000000"
				value={ color }
				aria-describedby={ describedBy }
				aria-required={ field.required || undefined }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</div>
	);
}
