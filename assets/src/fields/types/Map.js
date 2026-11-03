/**
 * Map field. Address and coordinates can always be typed in. When the field
 * uses a map service, the map bundle adds an interactive map above them.
 */
import { __ } from '@wordpress/i18n';

export default function Map( props ) {
	const { field, id, value, onChange, describedBy } = props;
	const empty = { lat: '', lng: '', address: '', zoom: '' };
	const place = value && typeof value === 'object' ? value : empty;
	// An updater, so an address arriving later from a lookup doesn't undo the pin move before it.
	const set = ( patch ) => onChange( ( current ) => ( { ...( current && typeof current === 'object' ? current : empty ), ...patch } ) );
	const provider = field.settings?.provider || 'none';
	const MapView = provider !== 'none' ? window.cfmb?.mapView : null;

	return (
		<div className="cfmb-map" aria-describedby={ describedBy }>
			{ MapView && <MapView { ...props } place={ place } onPlace={ set } /> }
			<div className="cfmb-map__inputs">
				<div className="cfmb-map__address">
					<label htmlFor={ id }>{ __( 'Address', 'custom-fields-meta-box' ) }</label>
					<input id={ id } className="cfmb-input" type="text" value={ place.address || '' } onChange={ ( event ) => set( { address: event.target.value } ) } />
				</div>
				<div className="cfmb-map__coordinate">
					<label htmlFor={ `${ id }-lat` }>{ __( 'Latitude', 'custom-fields-meta-box' ) }</label>
					<input id={ `${ id }-lat` } className="cfmb-input" type="text" inputMode="decimal" value={ place.lat ?? '' } onChange={ ( event ) => set( { lat: event.target.value } ) } />
				</div>
				<div className="cfmb-map__coordinate">
					<label htmlFor={ `${ id }-lng` }>{ __( 'Longitude', 'custom-fields-meta-box' ) }</label>
					<input id={ `${ id }-lng` } className="cfmb-input" type="text" inputMode="decimal" value={ place.lng ?? '' } onChange={ ( event ) => set( { lng: event.target.value } ) } />
				</div>
			</div>
		</div>
	);
}
