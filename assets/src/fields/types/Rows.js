/**
 * Repeater and Stacks fields: rows that can be added, collapsed, reordered,
 * duplicated, copied between posts and shown in pages.
 *
 * Collapsed rows don't render their fields until opened, which keeps long
 * lists fast. Paged rows load from the server only when their page opens;
 * pages never opened are sent back as references and left untouched.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, DropdownMenu, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import Fields from '../Fields';
import { joinPath, useForm } from '../context';
import { loadRows } from '../api';
import { validateSet } from '../validation';
import {
	cloneRow,
	fieldSignature,
	newRow,
	prepareRow,
	remapRow,
	rowFields,
} from '../values';
import { uiIcon } from '../../shared/ui-icons';

const CLIPBOARD_KEY = 'cfmb-row-clipboard';

function readClipboard() {
	try {
		const data = JSON.parse(
			window.localStorage.getItem( CLIPBOARD_KEY ) || 'null'
		);
		return data && Array.isArray( data.rows ) ? data : null;
	} catch ( error ) {
		return null;
	}
}

function writeClipboard( data ) {
	try {
		window.localStorage.setItem( CLIPBOARD_KEY, JSON.stringify( data ) );
		window.dispatchEvent( new window.Event( 'cfmb-clipboard' ) );
		return true;
	} catch ( error ) {
		return false;
	}
}

/**
 * Short text for a collapsed row's header.
 *
 * @param {*} value Sub-field value.
 * @return {string} Text.
 */
function summaryText( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	if ( typeof value === 'string' || typeof value === 'number' ) {
		return String( value )
			.replace( /<[^>]*>/g, '' )
			.trim();
	}
	if ( Array.isArray( value ) ) {
		return value.map( summaryText ).filter( Boolean ).join( ', ' );
	}
	if ( typeof value === 'object' ) {
		return summaryText(
			value.title ||
				value.label ||
				value.name ||
				value.address ||
				value.url ||
				value.filename ||
				''
		);
	}
	return '';
}

const TEXT_TYPES = new Set( [
	'text',
	'textarea',
	'email',
	'url',
	'number',
	'select',
	'radio',
	'button_group',
	'post',
	'link',
	'date',
	'datetime',
] );

function rowTitle( field, row ) {
	const fields = rowFields( field, row._layout );
	const chosen = fields.find(
		( sub ) => sub.key === field.settings?.row_title
	);
	const source =
		chosen ||
		fields.find(
			( sub ) =>
				TEXT_TYPES.has( sub.type ) && summaryText( row[ sub.key ] )
		);
	const text = source ? summaryText( row[ source.key ] ) : '';
	return text.length > 70 ? `${ text.slice( 0, 67 ) }…` : text;
}

export default function Rows( { field, value, onChange, path, id } ) {
	const form = useForm();
	const settings = field.settings || {};
	const isStacks = field.type === 'stacks';
	const rows = useMemo( () => value?.rows || [], [ value ] );
	const nested = path.includes( '.' );
	const paginate = !! settings.pagination && ! nested;
	const perPage = Math.max(
		1,
		Number( value?.perPage || settings.per_page ) || 20
	);
	const pageCount = paginate
		? Math.max( 1, Math.ceil( rows.length / perPage ) )
		: 1;
	const max = Number( settings.max ) || 0;
	const layouts = field.layouts || [];

	const [ expanded, setExpanded ] = useState( () =>
		settings.collapsed
			? new Set()
			: new Set( rows.map( ( row ) => row._uid ) )
	);
	const [ page, setPage ] = useState( 0 );
	const [ loading, setLoading ] = useState( false );
	const [ loadError, setLoadError ] = useState( '' );
	const [ removed, setRemoved ] = useState( null );
	const [ moving, setMoving ] = useState( null );
	const [ drag, setDrag ] = useState( null );
	const [ clipboard, setClipboard ] = useState( () => readClipboard() );
	const focusRef = useRef( null );
	const signature = useMemo( () => fieldSignature( field ), [ field ] );

	const update = ( change ) =>
		onChange( ( current ) => {
			const base =
				current && Array.isArray( current.rows )
					? current
					: { paged: false, perPage: 0, rows: [] };
			return { ...base, rows: change( base.rows ) };
		} );

	const setRowValue = ( uid, key, next ) =>
		update( ( list ) =>
			list.map( ( row ) =>
				row._uid === uid
					? {
							...row,
							[ key ]:
								typeof next === 'function'
									? next( row[ key ] )
									: next,
					  }
					: row
			)
		);

	const expand = ( uid, open = true ) =>
		setExpanded( ( current ) => {
			const next = new Set( current );
			if ( open ) {
				next.add( uid );
			} else {
				next.delete( uid );
			}
			return next;
		} );

	// Rows on the page being shown, with their position in the whole list.
	const pageStart = paginate ? Math.min( page, pageCount - 1 ) * perPage : 0;
	const shown = rows
		.slice( pageStart, paginate ? pageStart + perPage : rows.length )
		.map( ( row, offset ) => ( { row, index: pageStart + offset } ) );

	// Fetch rows of this page that were never loaded.
	const missing = shown
		.filter( ( { row } ) => row._unloaded )
		.map( ( { row } ) => row._from );
	const missingKey = missing.join( ',' );
	useEffect( () => {
		if ( ! missing.length || loading ) {
			return;
		}
		setLoading( true );
		setLoadError( '' );
		loadRows( {
			object: form.objectRef,
			group: form.groupKey,
			field: field.key,
			indexes: missing,
		} )
			.then( ( loaded ) => {
				const byFrom = new Map(
					loaded.map( ( row ) => [ row._from, row ] )
				);
				update( ( list ) =>
					list.map( ( row ) =>
						row._unloaded && byFrom.has( row._from )
							? prepareRow( field, {
									...byFrom.get( row._from ),
									_uid: row._uid,
							  } )
							: row
					)
				);
				if ( ! settings.collapsed ) {
					setExpanded(
						( current ) =>
							new Set( [
								...current,
								...shown.map( ( { row } ) => row._uid ),
							] )
					);
				}
			} )
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'These rows could not be loaded. Reload the page and try again.',
							'custom-fields-meta-box'
						)
				)
			)
			.finally( () => setLoading( false ) );
		// Runs when the set of missing rows changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ missingKey ] );

	// Open rows holding errors when errors are revealed, and go to the first one's page.
	useEffect( () => {
		if ( ! form.reveal ) {
			return;
		}
		const broken = rows
			.map( ( row, index ) => ( { row, index } ) )
			.filter(
				( { row, index } ) =>
					! row._unloaded &&
					validateSet(
						rowFields( field, row._layout ),
						row,
						form.reveal.enforceRequired,
						joinPath( path, index )
					).length
			);
		if ( ! broken.length ) {
			return;
		}
		setExpanded(
			( current ) =>
				new Set( [
					...current,
					...broken.map( ( { row } ) => row._uid ),
				] )
		);
		if ( paginate ) {
			setPage( Math.floor( broken[ 0 ].index / perPage ) );
		}
		// Only when a reveal is requested.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ form.reveal ] );

	useEffect( () => {
		const refresh = () => setClipboard( readClipboard() );
		window.addEventListener( 'cfmb-clipboard', refresh );
		window.addEventListener( 'storage', refresh );
		return () => {
			window.removeEventListener( 'cfmb-clipboard', refresh );
			window.removeEventListener( 'storage', refresh );
		};
	}, [] );

	useEffect( () => {
		if ( ! focusRef.current ) {
			return;
		}
		const node = document.querySelector(
			`[data-cfmb-row="${ focusRef.current }"]`
		);
		focusRef.current = null;
		node?.querySelector(
			'.cfmb-row__body input, .cfmb-row__body select, .cfmb-row__body textarea, .cfmb-row__toggle'
		)?.focus();
	} );

	const goToRow = ( index ) => {
		if ( paginate ) {
			setPage( Math.floor( index / perPage ) );
		}
	};

	const add = ( layout = '' ) => {
		const row = newRow( field, layout );
		update( ( list ) => [ ...list, row ] );
		expand( row._uid );
		goToRow( rows.length );
		focusRef.current = row._uid;
		speak( __( 'Row added.', 'custom-fields-meta-box' ) );
	};

	const remove = ( uid ) => {
		const index = rows.findIndex( ( row ) => row._uid === uid );
		setRemoved( { row: rows[ index ], index } );
		update( ( list ) => list.filter( ( row ) => row._uid !== uid ) );
		speak(
			__(
				'Row removed. Use Undo to bring it back.',
				'custom-fields-meta-box'
			)
		);
	};

	const undo = () => {
		if ( ! removed ) {
			return;
		}
		update( ( list ) => {
			const next = [ ...list ];
			next.splice(
				Math.min( removed.index, next.length ),
				0,
				removed.row
			);
			return next;
		} );
		goToRow( removed.index );
		setRemoved( null );
		speak( __( 'Row restored.', 'custom-fields-meta-box' ) );
	};

	const move = ( uid, target ) => {
		const from = rows.findIndex( ( row ) => row._uid === uid );
		const to = Math.max( 0, Math.min( rows.length - 1, target ) );
		if ( from < 0 || from === to ) {
			return;
		}
		update( ( list ) => {
			const next = [ ...list ];
			const [ row ] = next.splice( from, 1 );
			next.splice( to, 0, row );
			return next;
		} );
		goToRow( to );
		/* translators: %d: new row position */
		speak(
			sprintf(
				/* translators: %d: new position number */ __(
					'Row moved to position %d.',
					'custom-fields-meta-box'
				),
				to + 1
			)
		);
	};

	const duplicate = ( uid ) => {
		const index = rows.findIndex( ( row ) => row._uid === uid );
		const copy = cloneRow( field, rows[ index ] );
		update( ( list ) => {
			const next = [ ...list ];
			next.splice( index + 1, 0, copy );
			return next;
		} );
		expand( copy._uid );
		goToRow( index + 1 );
		speak( __( 'Row duplicated.', 'custom-fields-meta-box' ) );
	};

	const copy = ( list ) => {
		const loaded = list.filter( ( row ) => ! row._unloaded );
		// The definition travels along so rows can go into another field of the same shape.
		const saved = writeClipboard( {
			field: field.key,
			signature,
			definition: field,
			rows: loaded.map( ( row ) => cloneRow( field, row ) ),
		} );
		speak(
			saved
				? sprintf(
						/* translators: %d: number of rows */
						_n(
							'%d row copied. Paste it into this field, or one with the same sub-fields, on any post.',
							'%d rows copied. Paste them into this field, or one with the same sub-fields, on any post.',
							loaded.length,
							'custom-fields-meta-box'
						),
						loaded.length
				  )
				: __(
						'Rows could not be copied. Your browser blocks local storage.',
						'custom-fields-meta-box'
				  )
		);
	};

	const fits =
		!! clipboard &&
		( clipboard.field === field.key ||
			( !! clipboard.definition && clipboard.signature === signature ) );

	const paste = () => {
		if ( ! fits ) {
			return;
		}
		const room =
			max > 0 ? Math.max( 0, max - rows.length ) : clipboard.rows.length;
		const pasted = clipboard.rows
			.slice( 0, room )
			.map( ( row ) =>
				clipboard.field === field.key
					? cloneRow( field, row )
					: remapRow( clipboard.definition, field, row )
			);
		update( ( list ) => [ ...list, ...pasted ] );
		setExpanded(
			( current ) =>
				new Set( [ ...current, ...pasted.map( ( row ) => row._uid ) ] )
		);
		goToRow( rows.length );
		speak(
			sprintf(
				/* translators: %d: number of rows */
				_n(
					'%d row pasted.',
					'%d rows pasted.',
					pasted.length,
					'custom-fields-meta-box'
				),
				pasted.length
			)
		);
	};

	const layoutFull = ( layout ) =>
		Number( layout.max ) > 0 &&
		rows.filter( ( row ) => row._layout === layout.name ).length >=
			Number( layout.max );
	const full = max > 0 && rows.length >= max;
	const addLabel =
		settings.button_label || __( 'Add row', 'custom-fields-meta-box' );
	const canPaste = fits && clipboard.rows.length > 0 && ! full;

	const rowHasErrors = ( row, index ) => {
		const prefix = `${ joinPath( path, index ) }.`;
		if (
			Object.keys( form.serverErrors || {} ).some( ( key ) =>
				key.startsWith( prefix )
			)
		) {
			return true;
		}
		return (
			!! form.reveal &&
			! row._unloaded &&
			validateSet(
				rowFields( field, row._layout ),
				row,
				form.reveal.enforceRequired,
				joinPath( path, index )
			).length > 0
		);
	};

	const onDrop = ( event, index ) => {
		if ( ! drag ) {
			return;
		}
		event.preventDefault();
		const box = event.currentTarget.getBoundingClientRect();
		let target =
			event.clientY > box.top + box.height / 2 ? index + 1 : index;
		const from = rows.findIndex( ( row ) => row._uid === drag );
		if ( from < target ) {
			target -= 1;
		}
		move( drag, target );
		setDrag( null );
	};

	const renderRow = ( { row, index } ) => {
		const open = expanded.has( row._uid );
		const layout = isStacks
			? layouts.find( ( item ) => item.name === row._layout )
			: null;
		const title = row._unloaded ? '' : rowTitle( field, row );
		const bodyId = `${ id }-row-${ row._uid }`;
		const broken = rowHasErrors( row, index );
		const label = sprintf(
			/* translators: %d: row number */
			__( 'Row %d', 'custom-fields-meta-box' ),
			index + 1
		);

		return (
			<li
				key={ row._uid }
				className={ [
					'cfmb-row',
					open ? 'is-open' : '',
					broken ? 'has-error' : '',
					drag === row._uid ? 'is-dragging' : '',
				]
					.filter( Boolean )
					.join( ' ' ) }
				data-cfmb-row={ row._uid }
				onDragOver={ ( event ) => drag && event.preventDefault() }
				onDrop={ ( event ) => onDrop( event, index ) }
			>
				<div className="cfmb-row__header">
					<span
						className="cfmb-row__handle"
						draggable
						aria-hidden="true"
						title={ __(
							'Drag to reorder',
							'custom-fields-meta-box'
						) }
						onDragStart={ ( event ) => {
							event.dataTransfer.effectAllowed = 'move';
							event.dataTransfer.setData(
								'text/plain',
								row._uid
							);
							const node =
								event.currentTarget.closest( '.cfmb-row' );
							if ( node ) {
								event.dataTransfer.setDragImage( node, 20, 20 );
							}
							setDrag( row._uid );
						} }
						onDragEnd={ () => setDrag( null ) }
					>
						{ uiIcon( 'menu', 20 ) }
					</span>
					<button
						type="button"
						className="cfmb-row__toggle"
						aria-expanded={ open }
						aria-controls={ bodyId }
						onClick={ () => expand( row._uid, ! open ) }
					>
						<span className="cfmb-row__number">{ index + 1 }</span>
						{ layout && (
							<span className="cfmb-row__layout">
								{ layout.label }
							</span>
						) }
						<span className="cfmb-row__title">
							{ title || ( layout ? '' : label ) }
						</span>
						{ broken && (
							<span className="cfmb-row__flag">
								{ __(
									'Needs attention',
									'custom-fields-meta-box'
								) }
							</span>
						) }
						<span className="cfmb-row__chevron" aria-hidden="true">
							{ uiIcon(
								open ? 'arrow-up-alt2' : 'arrow-down-alt2',
								20
							) }
						</span>
					</button>
					<DropdownMenu
						icon={ uiIcon( 'ellipsis' ) }
						label={
							/* translators: %s: row label, like "Row 2" */
							sprintf(
								/* translators: %s: label */ __(
									'Actions for %s',
									'custom-fields-meta-box'
								),
								label
							)
						}
						toggleProps={ { size: 'compact' } }
						controls={ [
							{
								title: __(
									'Move up',
									'custom-fields-meta-box'
								),
								icon: uiIcon( 'arrow-up-alt' ),
								isDisabled: index === 0,
								onClick: () => move( row._uid, index - 1 ),
							},
							{
								title: __(
									'Move down',
									'custom-fields-meta-box'
								),
								icon: uiIcon( 'arrow-down-alt' ),
								isDisabled: index === rows.length - 1,
								onClick: () => move( row._uid, index + 1 ),
							},
							{
								title: __(
									'Move to position…',
									'custom-fields-meta-box'
								),
								icon: uiIcon( 'sort' ),
								isDisabled: rows.length < 2,
								onClick: () =>
									setMoving( {
										uid: row._uid,
										to: String( index + 1 ),
									} ),
							},
							{
								title: __(
									'Duplicate',
									'custom-fields-meta-box'
								),
								icon: uiIcon( 'admin-page' ),
								isDisabled:
									full ||
									row._unloaded ||
									( layout && layoutFull( layout ) ),
								onClick: () => duplicate( row._uid ),
							},
							{
								title: __( 'Copy', 'custom-fields-meta-box' ),
								icon: uiIcon( 'clipboard' ),
								isDisabled: row._unloaded,
								onClick: () => copy( [ row ] ),
							},
							{
								title: __( 'Remove', 'custom-fields-meta-box' ),
								icon: uiIcon( 'trash' ),
								onClick: () => remove( row._uid ),
							},
						] }
					/>
				</div>
				{ moving?.uid === row._uid && (
					<form
						className="cfmb-row__move"
						onSubmit={ ( event ) => {
							event.preventDefault();
							move( row._uid, Number( moving.to ) - 1 );
							setMoving( null );
						} }
					>
						<label htmlFor={ `${ bodyId }-move` }>
							{ __(
								'Move to position',
								'custom-fields-meta-box'
							) }
						</label>
						<input
							id={ `${ bodyId }-move` }
							className="cfmb-input cfmb-input--small"
							type="number"
							min={ 1 }
							max={ rows.length }
							value={ moving.to }
							// eslint-disable-next-line jsx-a11y/no-autofocus
							autoFocus
							onChange={ ( event ) =>
								setMoving( {
									...moving,
									to: event.target.value,
								} )
							}
						/>
						<Button
							variant="secondary"
							size="compact"
							type="submit"
						>
							{ __( 'Move', 'custom-fields-meta-box' ) }
						</Button>
						<Button
							variant="tertiary"
							size="compact"
							onClick={ () => setMoving( null ) }
						>
							{ __( 'Cancel', 'custom-fields-meta-box' ) }
						</Button>
					</form>
				) }
				{ open && (
					<div className="cfmb-row__body" id={ bodyId }>
						{ row._unloaded ? (
							<p className="cfmb-rows__loading">
								<Spinner />{ ' ' }
								{ __(
									'Loading row…',
									'custom-fields-meta-box'
								) }
							</p>
						) : (
							<Fields
								fields={ rowFields( field, row._layout ) }
								values={ row }
								path={ joinPath( path, index ) }
								onChange={ ( key, next ) =>
									setRowValue( row._uid, key, next )
								}
							/>
						) }
					</div>
				) }
			</li>
		);
	};

	return (
		<div
			className={ `cfmb-rows cfmb-rows--${ field.type }` }
			id={ id }
			tabIndex={ -1 }
		>
			{ rows.length > 0 && (
				<div className="cfmb-rows__toolbar">
					<span className="cfmb-rows__count">
						{ sprintf(
							/* translators: %d: number of rows */
							_n(
								'%d row',
								'%d rows',
								rows.length,
								'custom-fields-meta-box'
							),
							rows.length
						) }
					</span>
					<Button
						variant="link"
						size="compact"
						onClick={ () =>
							setExpanded(
								new Set( rows.map( ( row ) => row._uid ) )
							)
						}
					>
						{ __( 'Expand all', 'custom-fields-meta-box' ) }
					</Button>
					<Button
						variant="link"
						size="compact"
						onClick={ () => setExpanded( new Set() ) }
					>
						{ __( 'Collapse all', 'custom-fields-meta-box' ) }
					</Button>
					<Button
						variant="link"
						size="compact"
						onClick={ () => copy( rows ) }
					>
						{ __( 'Copy all', 'custom-fields-meta-box' ) }
					</Button>
				</div>
			) }

			{ rows.length === 0 ? (
				<p className="cfmb-rows__empty">
					{ isStacks
						? __(
								'No rows yet. Add one of the layouts below.',
								'custom-fields-meta-box'
						  )
						: __( 'No rows yet.', 'custom-fields-meta-box' ) }
				</p>
			) : (
				<ol className="cfmb-rows__list">{ shown.map( renderRow ) }</ol>
			) }

			{ loading && (
				<p className="cfmb-rows__loading" role="status">
					<Spinner />{ ' ' }
					{ __( 'Loading rows…', 'custom-fields-meta-box' ) }
				</p>
			) }
			{ loadError && (
				<p className="cfmb-field__error" role="alert">
					{ loadError }
				</p>
			) }

			{ removed && (
				<div className="cfmb-rows__undo" role="status">
					<span>
						{ __( 'Row removed.', 'custom-fields-meta-box' ) }
					</span>
					<Button variant="link" onClick={ undo }>
						{ __( 'Undo', 'custom-fields-meta-box' ) }
					</Button>
					<Button
						variant="link"
						className="cfmb-rows__undo-dismiss"
						label={ __( 'Dismiss', 'custom-fields-meta-box' ) }
						icon={ uiIcon( 'no-alt' ) }
						size="small"
						onClick={ () => setRemoved( null ) }
					/>
				</div>
			) }

			{ paginate && pageCount > 1 && (
				<nav
					className="cfmb-rows__pages"
					aria-label={ __( 'Row pages', 'custom-fields-meta-box' ) }
				>
					<Button
						variant="tertiary"
						size="compact"
						icon={ uiIcon( 'arrow-left-alt2' ) }
						label={ __(
							'Previous page',
							'custom-fields-meta-box'
						) }
						disabled={ page === 0 }
						onClick={ () => setPage( page - 1 ) }
					/>
					<label>
						<span className="screen-reader-text">
							{ __( 'Page', 'custom-fields-meta-box' ) }
						</span>
						<select
							className="cfmb-input cfmb-input--small"
							value={ Math.min( page, pageCount - 1 ) }
							onChange={ ( event ) =>
								setPage( Number( event.target.value ) )
							}
						>
							{ Array.from(
								{ length: pageCount },
								( _, index ) => (
									<option key={ index } value={ index }>
										{ sprintf(
											/* translators: 1: page number, 2: number of pages */
											__(
												'Page %1$d of %2$d',
												'custom-fields-meta-box'
											),
											index + 1,
											pageCount
										) }
									</option>
								)
							) }
						</select>
					</label>
					<Button
						variant="tertiary"
						size="compact"
						icon={ uiIcon( 'arrow-right-alt2' ) }
						label={ __( 'Next page', 'custom-fields-meta-box' ) }
						disabled={ page >= pageCount - 1 }
						onClick={ () => setPage( page + 1 ) }
					/>
				</nav>
			) }

			<div className="cfmb-rows__actions">
				{ isStacks ? (
					<DropdownMenu
						icon={ uiIcon( 'plus-alt2' ) }
						text={
							settings.button_label ||
							__( 'Add row', 'custom-fields-meta-box' )
						}
						label={ __(
							'Choose a layout for the new row',
							'custom-fields-meta-box'
						) }
						toggleProps={ {
							variant: 'secondary',
							disabled: full || layouts.length === 0,
						} }
						controls={ layouts.map( ( layout ) => ( {
							title: layout.label,
							isDisabled: layoutFull( layout ),
							onClick: () => add( layout.name ),
						} ) ) }
					/>
				) : (
					<Button
						variant="secondary"
						icon={ uiIcon( 'plus-alt2' ) }
						disabled={ full }
						onClick={ () => add() }
					>
						{ addLabel }
					</Button>
				) }
				{ canPaste && (
					<Button
						variant="tertiary"
						icon={ uiIcon( 'clipboard' ) }
						onClick={ paste }
					>
						{ sprintf(
							/* translators: %d: number of rows */
							_n(
								'Paste %d row',
								'Paste %d rows',
								clipboard.rows.length,
								'custom-fields-meta-box'
							),
							clipboard.rows.length
						) }
					</Button>
				) }
				{ full && (
					<span className="cfmb-rows__limit">
						{ __( 'Row limit reached.', 'custom-fields-meta-box' ) }
					</span>
				) }
			</div>
		</div>
	);
}
