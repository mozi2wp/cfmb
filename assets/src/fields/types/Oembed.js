/**
 * oEmbed field: a URL with a live preview from WordPress's oEmbed proxy.
 */
import { useEffect, useState } from '@wordpress/element';
import { SandBox, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { embedPreview } from '../api';

export default function Oembed( { field, id, value, onChange, describedBy } ) {
	const [ preview, setPreview ] = useState( { url: '', html: '', error: '', loading: false } );
	const url = typeof value === 'string' ? value.trim() : '';

	useEffect( () => {
		if ( ! /^https?:\/\/\S+\.\S+/.test( url ) ) {
			setPreview( { url: '', html: '', error: '', loading: false } );
			return undefined;
		}
		let cancelled = false;
		const timer = window.setTimeout( () => {
			setPreview( ( current ) => ( { ...current, loading: true, error: '' } ) );
			embedPreview( url )
				.then( ( response ) => ! cancelled && setPreview( { url, html: response?.html || '', error: response?.html ? '' : __( 'No preview for this address.', 'custom-fields-meta-box' ), loading: false } ) )
				.catch( () => ! cancelled && setPreview( { url, html: '', error: __( 'This address can\'t be embedded. It is saved as a plain link.', 'custom-fields-meta-box' ), loading: false } ) );
		}, 600 );
		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ url ] );

	return (
		<div className="cfmb-oembed">
			<input
				id={ id }
				className="cfmb-input"
				type="url"
				placeholder={ __( 'Paste a YouTube, Vimeo or other link', 'custom-fields-meta-box' ) }
				value={ value ?? '' }
				aria-describedby={ describedBy }
				aria-required={ field.required || undefined }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
			{ preview.loading && (
				<p className="cfmb-oembed__status">
					<Spinner /> { __( 'Loading preview…', 'custom-fields-meta-box' ) }
				</p>
			) }
			{ preview.error && <p className="cfmb-oembed__status">{ preview.error }</p> }
			{ preview.html && ! preview.loading && (
				<div className="cfmb-oembed__preview">
					<SandBox html={ preview.html } title={ __( 'Embed preview', 'custom-fields-meta-box' ) } />
				</div>
			) }
		</div>
	);
}
