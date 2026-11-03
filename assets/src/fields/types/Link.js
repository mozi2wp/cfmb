/**
 * Link field: address, text and new-tab choice.
 */
import { __ } from '@wordpress/i18n';

export default function Link( { id, value, onChange, describedBy, field } ) {
	const link = value && typeof value === 'object' ? value : { url: '', title: '', target: '' };
	const set = ( patch ) => onChange( { ...link, ...patch } );

	return (
		<div className="cfmb-link" aria-describedby={ describedBy }>
			<div className="cfmb-link__row">
				<label className="cfmb-link__label" htmlFor={ id }>
					{ __( 'Address', 'cfmb' ) }
				</label>
				<input
					id={ id }
					className="cfmb-input"
					type="url"
					placeholder="https://"
					value={ link.url || '' }
					aria-required={ field.required || undefined }
					onChange={ ( event ) => set( { url: event.target.value } ) }
				/>
			</div>
			<div className="cfmb-link__row">
				<label className="cfmb-link__label" htmlFor={ `${ id }-text` }>
					{ __( 'Link text', 'cfmb' ) }
				</label>
				<input id={ `${ id }-text` } className="cfmb-input" type="text" value={ link.title || '' } onChange={ ( event ) => set( { title: event.target.value } ) } />
			</div>
			<label className="cfmb-link__target">
				<input type="checkbox" checked={ link.target === '_blank' } onChange={ ( event ) => set( { target: event.target.checked ? '_blank' : '' } ) } />
				{ __( 'Open in a new tab', 'cfmb' ) }
			</label>
		</div>
	);
}
