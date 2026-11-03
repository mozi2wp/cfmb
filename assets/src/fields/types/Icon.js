/**
 * Icon field: pick from icon libraries, the media library or a URL.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Modal, Spinner, TabPanel } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { loadIcons } from '../api';

export function IconPreview( { preview } ) {
	if ( ! preview?.src ) {
		return null;
	}
	if ( preview.kind === 'img' ) {
		return <img className="cfmb-icon-preview" src={ preview.src } alt="" />;
	}
	if ( preview.kind === 'svg' ) {
		// SVG markup comes from CFMB's bundled icon file, served by our own REST route.
		return (
			<span
				className="cfmb-icon-preview"
				aria-hidden="true"
				dangerouslySetInnerHTML={ { __html: preview.src } }
			/>
		);
	}
	return (
		<span
			className={ `cfmb-icon-preview ${ preview.src }` }
			aria-hidden="true"
		/>
	);
}

// Large libraries such as Font Awesome have thousands of icons; draw them a page at a time.
const PAGE = 240;

function LibraryGrid( { library, current, onPick } ) {
	const [ icons, setIcons ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ limit, setLimit ] = useState( PAGE );

	useEffect( () => {
		loadIcons( library )
			.then( setIcons )
			.catch( ( failure ) =>
				setError(
					failure.message ||
						__(
							'Icons could not be loaded.',
							'custom-fields-meta-box'
						)
				)
			);
	}, [ library ] );

	const shown = useMemo( () => {
		const text = query.trim().toLowerCase();
		return ( icons || [] ).filter(
			( icon ) =>
				! text ||
				icon.name.includes( text ) ||
				( icon.label || '' ).toLowerCase().includes( text )
		);
	}, [ icons, query ] );

	if ( error ) {
		return <p className="cfmb-field__error">{ error }</p>;
	}
	if ( ! icons ) {
		return <Spinner />;
	}

	return (
		<div className="cfmb-icon-picker__library">
			<input
				className="cfmb-input"
				type="search"
				placeholder={ __( 'Search icons…', 'custom-fields-meta-box' ) }
				aria-label={ __( 'Search icons', 'custom-fields-meta-box' ) }
				value={ query }
				// eslint-disable-next-line jsx-a11y/no-autofocus
				autoFocus
				onChange={ ( event ) => {
					setQuery( event.target.value );
					setLimit( PAGE );
				} }
			/>
			<p className="cfmb-icon-picker__count" aria-live="polite">
				{ sprintf(
					/* translators: %d: number of icons */
					__( '%d icons', 'custom-fields-meta-box' ),
					shown.length
				) }
			</p>
			<div className="cfmb-icon-picker__grid">
				{ shown.slice( 0, limit ).map( ( icon ) => {
					const value = `${ library }:${ icon.name }`;
					const preview = icon.svg
						? { kind: 'svg', src: icon.svg }
						: { kind: 'class', src: icon.class };
					return (
						<button
							key={ icon.name }
							type="button"
							className={ `cfmb-icon-picker__icon${
								current === value ? ' is-selected' : ''
							}` }
							aria-pressed={ current === value }
							title={ icon.label }
							onClick={ () => onPick( { value, preview } ) }
						>
							<IconPreview preview={ preview } />
							<span className="screen-reader-text">
								{ icon.label }
							</span>
						</button>
					);
				} ) }
			</div>
			{ shown.length > limit && (
				<Button
					variant="secondary"
					className="cfmb-icon-picker__more"
					onClick={ () => setLimit( limit + PAGE ) }
				>
					{ __( 'Show more icons', 'custom-fields-meta-box' ) }
				</Button>
			) }
		</div>
	);
}

function UrlTab( { onPick } ) {
	const [ url, setUrl ] = useState( '' );
	return (
		<form
			className="cfmb-icon-picker__url"
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( /^https?:\/\//.test( url.trim() ) ) {
					onPick( {
						value: `url:${ url.trim() }`,
						preview: { kind: 'img', src: url.trim() },
					} );
				}
			} }
		>
			<label htmlFor="cfmb-icon-url">
				{ __( 'Image address', 'custom-fields-meta-box' ) }
			</label>
			<input
				id="cfmb-icon-url"
				className="cfmb-input"
				type="url"
				placeholder="https://"
				value={ url }
				onChange={ ( event ) => setUrl( event.target.value ) }
			/>
			<Button
				variant="primary"
				type="submit"
				disabled={ ! /^https?:\/\//.test( url.trim() ) }
			>
				{ __( 'Use this image', 'custom-fields-meta-box' ) }
			</Button>
		</form>
	);
}

export default function Icon( { field, id, value, onChange, describedBy } ) {
	const [ open, setOpen ] = useState( false );
	const buttonRef = useRef();
	const libraries = window.cfmbFields?.iconLibraries || {};
	const allowed =
		Array.isArray( field.settings?.sources ) &&
		field.settings.sources.length
			? field.settings.sources
			: [ ...Object.keys( libraries ), 'media', 'url' ];
	const current = value?.value || '';

	const pick = ( next ) => {
		onChange( next );
		setOpen( false );
		buttonRef.current?.focus();
	};

	const openMedia = () => {
		const media = window.wp?.media;
		if ( ! media ) {
			return;
		}
		setOpen( false );
		const frame = media( {
			title: __( 'Choose an icon image', 'custom-fields-meta-box' ),
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			const src = attachment.sizes?.thumbnail?.url || attachment.url;
			pick( {
				value: `media:${ attachment.id }`,
				preview: { kind: 'img', src },
			} );
		} );
		frame.open();
	};

	const tabs = allowed
		.filter(
			( source ) =>
				libraries[ source ] || source === 'media' || source === 'url'
		)
		.map( ( source ) => ( {
			name: source,
			title:
				libraries[ source ] ||
				( source === 'media'
					? __( 'Media library', 'custom-fields-meta-box' )
					: __( 'Image URL', 'custom-fields-meta-box' ) ),
		} ) );

	return (
		<div className="cfmb-icon-field" aria-describedby={ describedBy }>
			{ value?.preview && (
				<span className="cfmb-icon-field__current">
					<IconPreview preview={ value.preview } />
				</span>
			) }
			<Button
				id={ id }
				ref={ buttonRef }
				variant="secondary"
				onClick={ () => setOpen( true ) }
			>
				{ value
					? __( 'Change icon', 'custom-fields-meta-box' )
					: __( 'Choose icon', 'custom-fields-meta-box' ) }
			</Button>
			{ value && (
				<Button
					variant="tertiary"
					isDestructive
					onClick={ () => onChange( null ) }
				>
					{ __( 'Remove', 'custom-fields-meta-box' ) }
				</Button>
			) }
			{ open && (
				<Modal
					title={ __( 'Choose an icon', 'custom-fields-meta-box' ) }
					onRequestClose={ () => setOpen( false ) }
					className="cfmb-icon-picker"
					size="large"
				>
					<TabPanel
						tabs={ tabs }
						initialTabName={
							current.split( ':' )[ 0 ] &&
							tabs.some(
								( tab ) =>
									tab.name === current.split( ':' )[ 0 ]
							)
								? current.split( ':' )[ 0 ]
								: undefined
						}
					>
						{ ( tab ) => {
							if ( tab.name === 'media' ) {
								return (
									<div className="cfmb-icon-picker__media">
										<Button
											variant="secondary"
											onClick={ openMedia }
										>
											{ __(
												'Open the media library',
												'custom-fields-meta-box'
											) }
										</Button>
									</div>
								);
							}
							if ( tab.name === 'url' ) {
								return <UrlTab onPick={ pick } />;
							}
							return (
								<LibraryGrid
									library={ tab.name }
									current={ current }
									onPick={ pick }
								/>
							);
						} }
					</TabPanel>
				</Modal>
			) }
		</div>
	);
}
