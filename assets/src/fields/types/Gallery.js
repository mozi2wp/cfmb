/**
 * Gallery field: several images in order, picked from the media library.
 */
import { useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';

function toItem( attachment, previewSize ) {
	const sizes = attachment.sizes || {};
	const preview = sizes[ previewSize ] || sizes.medium || sizes.thumbnail || sizes.full;
	return {
		id: attachment.id,
		url: attachment.url,
		preview: preview ? preview.url : attachment.url,
		title: attachment.title,
		alt: attachment.alt || '',
	};
}

export default function Gallery( { field, id, value, onChange, describedBy, objectId } ) {
	const settings = field.settings || {};
	const items = Array.isArray( value ) ? value : [];
	const frameRef = useRef();
	const [ drag, setDrag ] = useState( null );
	const max = Number( settings.max ) || 0;

	const open = () => {
		const media = window.wp?.media;
		if ( ! media ) {
			return;
		}
		const library = { type: 'image' };
		if ( settings.library === 'uploaded' && objectId ) {
			library.uploadedTo = objectId;
		}
		frameRef.current = media( {
			title: __( 'Add images', 'cfmb' ),
			button: { text: __( 'Add to gallery', 'cfmb' ) },
			library,
			multiple: 'add',
		} );
		frameRef.current.on( 'select', () => {
			const chosen = frameRef.current
				.state()
				.get( 'selection' )
				.toJSON()
				.map( ( attachment ) => toItem( attachment, settings.preview_size || 'medium' ) );
			onChange( ( current ) => {
				const list = Array.isArray( current ) ? current : [];
				const known = new Set( list.map( ( item ) => item.id ) );
				const next = [ ...list, ...chosen.filter( ( item ) => ! known.has( item.id ) ) ];
				return max > 0 ? next.slice( 0, max ) : next;
			} );
		} );
		frameRef.current.open();
	};

	const move = ( from, to ) => {
		if ( to < 0 || to >= items.length ) {
			return;
		}
		const next = [ ...items ];
		const [ item ] = next.splice( from, 1 );
		next.splice( to, 0, item );
		onChange( next );
		/* translators: %d: new position */
		speak( sprintf( __( 'Image moved to position %d.', 'cfmb' ), to + 1 ) );
	};

	return (
		<div className="cfmb-gallery" aria-describedby={ describedBy }>
			{ items.length > 0 && (
				<ul className="cfmb-gallery__grid">
					{ items.map( ( item, index ) => (
						<li
							key={ item.id }
							className={ `cfmb-gallery__item${ drag === index ? ' is-dragging' : '' }` }
							draggable
							onDragStart={ ( event ) => {
								event.dataTransfer.effectAllowed = 'move';
								event.dataTransfer.setData( 'text/plain', String( item.id ) );
								setDrag( index );
							} }
							onDragEnd={ () => setDrag( null ) }
							onDragOver={ ( event ) => drag !== null && event.preventDefault() }
							onDrop={ ( event ) => {
								event.preventDefault();
								if ( drag !== null ) {
									move( drag, index );
								}
								setDrag( null );
							} }
						>
							<img src={ item.preview || item.url } alt={ item.alt } />
							<div className="cfmb-gallery__item-actions">
								<Button size="small" icon="arrow-left-alt2" label={ __( 'Move earlier', 'cfmb' ) } disabled={ index === 0 } onClick={ () => move( index, index - 1 ) } />
								<Button size="small" icon="arrow-right-alt2" label={ __( 'Move later', 'cfmb' ) } disabled={ index === items.length - 1 } onClick={ () => move( index, index + 1 ) } />
								<Button
									size="small"
									icon="no-alt"
									isDestructive
									label={
										/* translators: %s: image title */
										sprintf( __( 'Remove %s', 'cfmb' ), item.title || __( 'image', 'cfmb' ) )
									}
									onClick={ () => {
										onChange( items.filter( ( other ) => other.id !== item.id ) );
										speak( __( 'Image removed.', 'cfmb' ) );
									} }
								/>
							</div>
						</li>
					) ) }
				</ul>
			) }
			<div className="cfmb-gallery__actions">
				<Button id={ id } variant="secondary" icon="format-gallery" disabled={ max > 0 && items.length >= max } onClick={ open }>
					{ items.length ? __( 'Add more images', 'cfmb' ) : __( 'Add images', 'cfmb' ) }
				</Button>
				{ items.length > 0 && (
					<Button variant="tertiary" isDestructive onClick={ () => onChange( [] ) }>
						{ __( 'Remove all', 'cfmb' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
