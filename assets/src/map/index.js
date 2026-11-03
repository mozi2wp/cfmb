/**
 * Interactive map for Map fields, loaded only on screens that show one with
 * a map service. OpenStreetMap uses Leaflet (bundled); Google Maps loads
 * Google's script with the site's API key.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import './map.scss';

const FALLBACK = { lat: 20, lng: 0, zoom: 2 };

function startView( field, place ) {
	const settings = field.settings || {};
	if (
		place.lat !== '' &&
		place.lng !== '' &&
		Number.isFinite( Number( place.lat ) )
	) {
		return {
			lat: Number( place.lat ),
			lng: Number( place.lng ),
			zoom: Number( place.zoom ) || Number( settings.zoom ) || 13,
		};
	}
	if (
		settings.center_lat !== '' &&
		settings.center_lat !== undefined &&
		Number.isFinite( Number( settings.center_lat ) )
	) {
		return {
			lat: Number( settings.center_lat ),
			lng: Number( settings.center_lng ) || 0,
			zoom: Number( settings.zoom ) || 13,
		};
	}
	return FALLBACK;
}

const round = ( number ) => String( Math.round( number * 1e7 ) / 1e7 );

/**
 * Address search: Nominatim for OpenStreetMap, the Geocoder for Google.
 *
 * @param {string} provider osm or google.
 * @param {string} query    Text.
 * @return {Promise<Array<{label: string, lat: number, lng: number}>>} Places.
 */
function geocode( provider, query ) {
	if ( provider === 'google' ) {
		return new Promise( ( resolve, reject ) => {
			new window.google.maps.Geocoder().geocode(
				{ address: query },
				( results, status ) => {
					if ( status === 'OK' ) {
						resolve(
							results
								.slice( 0, 5 )
								.map( ( result ) => ( {
									label: result.formatted_address,
									lat: result.geometry.location.lat(),
									lng: result.geometry.location.lng(),
								} ) )
						);
					} else if ( status === 'ZERO_RESULTS' ) {
						resolve( [] );
					} else {
						reject( new Error( status ) );
					}
				}
			);
		} );
	}
	const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&q=${ encodeURIComponent(
		query
	) }&accept-language=${ encodeURIComponent(
		document.documentElement.lang || 'en'
	) }`;
	return window
		.fetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( response ) =>
			response.ok
				? response.json()
				: Promise.reject( new Error( response.statusText ) )
		)
		.then( ( places ) =>
			places.map( ( place ) => ( {
				label: place.display_name,
				lat: Number( place.lat ),
				lng: Number( place.lon ),
			} ) )
		);
}

/**
 * Address for a point: Nominatim's reverse lookup, or Google's Geocoder.
 *
 * @param {string} provider osm or google.
 * @param {number} lat      Latitude.
 * @param {number} lng      Longitude.
 * @return {Promise<string>} Address, or an empty string.
 */
function reverseGeocode( provider, lat, lng ) {
	if ( provider === 'google' ) {
		return new Promise( ( resolve ) => {
			new window.google.maps.Geocoder().geocode(
				{ location: { lat, lng } },
				( results, status ) =>
					resolve(
						status === 'OK' && results[ 0 ]
							? results[ 0 ].formatted_address
							: ''
					)
			);
		} );
	}
	const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${ lat }&lon=${ lng }&accept-language=${ encodeURIComponent(
		document.documentElement.lang || 'en'
	) }`;
	return window
		.fetch( url, { headers: { Accept: 'application/json' } } )
		.then( ( response ) => ( response.ok ? response.json() : {} ) )
		.then( ( place ) => place?.display_name || '' )
		.catch( () => '' );
}

let googleLoading = null;
function loadGoogle( key ) {
	if ( window.google?.maps?.Map ) {
		return Promise.resolve();
	}
	if ( ! googleLoading ) {
		googleLoading = new Promise( ( resolve, reject ) => {
			window.cfmbGoogleMapsReady = () => resolve();
			const script = document.createElement( 'script' );
			script.src = `https://maps.googleapis.com/maps/api/js?key=${ encodeURIComponent(
				key
			) }&callback=cfmbGoogleMapsReady&loading=async&v=weekly&libraries=marker`;
			script.async = true;
			script.onerror = () => {
				googleLoading = null;
				reject( new Error( 'load' ) );
			};
			document.head.appendChild( script );
		} );
	}
	return googleLoading;
}

/**
 * Leaflet or Google map with a draggable pin.
 *
 * @param {Object} props { provider, field, place, onPoint, onZoom }.
 * @return {Element} Map.
 */
function Canvas( { provider, field, place, onPoint, onZoom } ) {
	const node = useRef();
	const map = useRef();
	const marker = useRef();
	const handlers = useRef( {} );
	handlers.current = { onPoint, onZoom };
	const [ status, setStatus ] = useState(
		provider === 'google' ? 'loading' : 'ready'
	);

	useEffect( () => {
		const view = startView( field, place );
		let observer;

		if ( provider === 'google' ) {
			loadGoogle( window.cfmbFields?.googleKey || '' )
				.then( () => {
					const maps = window.google.maps;
					const mapId = window.cfmbFields?.googleMapId || '';
					map.current = new maps.Map( node.current, {
						center: view,
						zoom: view.zoom,
						streetViewControl: false,
						mapTypeControl: false,
						...( mapId ? { mapId } : {} ),
					} );

					// Advanced Markers need a Map ID; without one the classic marker still works.
					if ( mapId && maps.marker?.AdvancedMarkerElement ) {
						const pin = new maps.marker.AdvancedMarkerElement( {
							gmpDraggable: true,
							title: __( 'Place', 'custom-fields-meta-box' ),
						} );
						marker.current = {
							show: ( point ) => {
								pin.position = point;
								pin.map = map.current;
							},
							hide: () => ( pin.map = null ),
						};
						pin.addListener( 'dragend', () => {
							const point = pin.position;
							const lat =
								typeof point.lat === 'function'
									? point.lat()
									: point.lat;
							const lng =
								typeof point.lng === 'function'
									? point.lng()
									: point.lng;
							handlers.current.onPoint( lat, lng );
						} );
					} else {
						const pin = new maps.Marker( {
							map: map.current,
							draggable: true,
							visible: false,
						} );
						marker.current = {
							show: ( point ) => {
								pin.setPosition( point );
								pin.setVisible( true );
							},
							hide: () => pin.setVisible( false ),
						};
						pin.addListener( 'dragend', ( event ) =>
							handlers.current.onPoint(
								event.latLng.lat(),
								event.latLng.lng()
							)
						);
					}

					if ( place.lat !== '' ) {
						marker.current.show( { lat: view.lat, lng: view.lng } );
					}
					map.current.addListener( 'click', ( event ) =>
						handlers.current.onPoint(
							event.latLng.lat(),
							event.latLng.lng()
						)
					);
					map.current.addListener( 'zoom_changed', () =>
						handlers.current.onZoom( map.current.getZoom() )
					);
					setStatus( 'ready' );
				} )
				.catch( () => setStatus( 'error' ) );
			return undefined;
		}

		map.current = L.map( node.current, { scrollWheelZoom: false } ).setView(
			[ view.lat, view.lng ],
			view.zoom
		);
		L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		} ).addTo( map.current );
		marker.current = L.marker( [ view.lat, view.lng ], {
			draggable: true,
			keyboard: true,
			// Leaflet positions the icon element with a transform, so the shape is drawn on a child.
			icon: L.divIcon( {
				className: 'cfmb-map__pin',
				html: '<span class="cfmb-map__pin-shape"></span>',
				iconSize: [ 26, 26 ],
				iconAnchor: [ 13, 26 ],
			} ),
		} );
		if ( place.lat !== '' ) {
			marker.current.addTo( map.current );
		}
		map.current.on( 'click', ( event ) =>
			handlers.current.onPoint( event.latlng.lat, event.latlng.lng )
		);
		marker.current.on( 'dragend', () => {
			const point = marker.current.getLatLng();
			handlers.current.onPoint( point.lat, point.lng );
		} );
		map.current.on( 'zoomend', () =>
			handlers.current.onZoom( map.current.getZoom() )
		);

		// Maps inside closed tabs, accordions or rows need a size check once they show.
		if ( window.ResizeObserver ) {
			observer = new window.ResizeObserver( () =>
				map.current?.invalidateSize()
			);
			observer.observe( node.current );
		}

		return () => {
			observer?.disconnect();
			map.current?.remove();
			map.current = null;
		};
		// The map is created once; later changes are applied below.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ provider ] );

	// Follow typed coordinates and search results.
	useEffect( () => {
		if ( ! map.current || ! marker.current || status !== 'ready' ) {
			return;
		}
		const lat = Number( place.lat );
		const lng = Number( place.lng );
		const valid =
			place.lat !== '' &&
			place.lng !== '' &&
			Number.isFinite( lat ) &&
			Number.isFinite( lng ) &&
			Math.abs( lat ) <= 90 &&
			Math.abs( lng ) <= 180;

		if ( provider === 'google' ) {
			if ( ! valid ) {
				marker.current.hide();
				return;
			}
			marker.current.show( { lat, lng } );
			if ( ! map.current.getBounds()?.contains( { lat, lng } ) ) {
				map.current.panTo( { lat, lng } );
			}
			return;
		}

		if ( ! valid ) {
			marker.current.remove();
			return;
		}
		marker.current.setLatLng( [ lat, lng ] );
		if ( ! map.current.hasLayer( marker.current ) ) {
			marker.current.addTo( map.current );
		}
		if ( ! map.current.getBounds().contains( [ lat, lng ] ) ) {
			map.current.panTo( [ lat, lng ] );
		}
	}, [ place.lat, place.lng, provider, status ] );

	return (
		<div className="cfmb-map__canvas-wrap">
			<div
				ref={ node }
				className="cfmb-map__canvas"
				style={ {
					height: `${ Number( field.settings?.height ) || 320 }px`,
				} }
				role="application"
				aria-label={ __(
					'Map. Click to place the pin, or drag the pin to move it.',
					'custom-fields-meta-box'
				) }
			/>
			{ status === 'loading' && (
				<p className="cfmb-map__status">
					<Spinner />{ ' ' }
					{ __( 'Loading map…', 'custom-fields-meta-box' ) }
				</p>
			) }
			{ status === 'error' && (
				<p className="cfmb-field__error">
					{ __(
						'Google Maps could not load. Check the API key in CFMB settings.',
						'custom-fields-meta-box'
					) }
				</p>
			) }
		</div>
	);
}

function MapView( { field, id, place, onPlace } ) {
	const provider = field.settings?.provider === 'google' ? 'google' : 'osm';
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const placeRef = useRef( place );
	placeRef.current = place;

	const lookup = useRef( 0 );

	// Placing the pin also fills in the address, once the lookup answers.
	const onPoint = ( lat, lng ) => {
		onPlace( { lat: round( lat ), lng: round( lng ) } );
		const request = ++lookup.current;
		reverseGeocode( provider, lat, lng ).then( ( address ) => {
			if ( address && request === lookup.current ) {
				onPlace( { address } );
				/* translators: %s: address */
				speak(
					sprintf(
						__( 'Address set to %s.', 'custom-fields-meta-box' ),
						address
					)
				);
			}
		} );
	};
	const onZoom = ( zoom ) =>
		placeRef.current.lat !== '' && onPlace( { zoom } );

	const find = () => {
		if ( ! query.trim() ) {
			return;
		}
		setBusy( true );
		setError( '' );
		geocode( provider, query.trim() )
			.then( ( places ) => {
				setResults( places );
				speak(
					places.length
						? __(
								'Places found. Choose one below the search box.',
								'custom-fields-meta-box'
						  )
						: __( 'No places found.', 'custom-fields-meta-box' )
				);
			} )
			.catch( () =>
				setError(
					__(
						'Address search is not available right now.',
						'custom-fields-meta-box'
					)
				)
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="cfmb-map__view">
			<div className="cfmb-map__search">
				<label
					className="screen-reader-text"
					htmlFor={ `${ id }-search` }
				>
					{ __( 'Search for an address', 'custom-fields-meta-box' ) }
				</label>
				<input
					id={ `${ id }-search` }
					className="cfmb-input"
					type="search"
					placeholder={ __(
						'Search for an address',
						'custom-fields-meta-box'
					) }
					value={ query }
					onChange={ ( event ) => setQuery( event.target.value ) }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.preventDefault();
							find();
						}
					} }
				/>
				<Button variant="secondary" isBusy={ busy } onClick={ find }>
					{ __( 'Search', 'custom-fields-meta-box' ) }
				</Button>
			</div>
			{ error && <p className="cfmb-field__error">{ error }</p> }
			{ results && results.length > 0 && (
				<ul className="cfmb-map__results">
					{ results.map( ( result ) => (
						<li key={ `${ result.lat },${ result.lng }` }>
							<button
								type="button"
								className="button-link"
								onClick={ () => {
									onPlace( {
										lat: round( result.lat ),
										lng: round( result.lng ),
										address: result.label,
										zoom: placeRef.current.zoom || 15,
									} );
									setResults( null );
								} }
							>
								{ result.label }
							</button>
						</li>
					) ) }
				</ul>
			) }
			<Canvas
				provider={ provider }
				field={ field }
				place={ place }
				onPoint={ onPoint }
				onZoom={ onZoom }
			/>
		</div>
	);
}

window.cfmb = window.cfmb || {};
window.cfmb.mapView = MapView;
