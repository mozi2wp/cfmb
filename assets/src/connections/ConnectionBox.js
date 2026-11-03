/**
 * One Connection box: search the other side, keep an ordered list.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { applyFilters } from '@wordpress/hooks';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { moveItem, toggleItem } from './list';

const settings = window.cfmbConnections || {};

const PLACEHOLDERS = {
	post: __( 'Search posts…', 'custom-fields-meta-box' ),
	term: __( 'Search terms…', 'custom-fields-meta-box' ),
	user: __( 'Search users…', 'custom-fields-meta-box' ),
};

function search( config, text, page ) {
	return apiFetch( {
		path: addQueryArgs( `${ settings.searchPath || '/cfmb/v1/connections' }/${ config.connection }/search`, {
			side: config.side,
			object: config.object,
			search: text,
			page,
		} ),
	} );
}

/**
 * Extra content add-ons show under a linked item, such as fields for the link.
 *
 * Filter: cfmb.connections.itemExtra( element|null, item, config, index ).
 *
 * @param {Object} props        Props.
 * @param {Object} props.item   Linked item.
 * @param {Object} props.config Box config.
 * @param {number} props.index  Position in the list.
 * @return {?Element} Element or null.
 */
function ItemExtra( { item, config, index } ) {
	const extra = applyFilters( 'cfmb.connections.itemExtra', null, item, config, index );

	return extra ? <div className="cfmb-connection__extra">{ extra }</div> : null;
}

function ItemText( { item } ) {
	return (
		<span className="cfmb-connection__text">
			<span className="cfmb-connection__title">{ item.title }</span>
			{ item.meta && <span className="cfmb-connection__meta">{ item.meta }</span> }
		</span>
	);
}

export default function ConnectionBox( { config } ) {
	const [ selected, setSelected ] = useState( Array.isArray( config.items ) ? config.items : [] );
	const [ text, setText ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ more, setMore ] = useState( false );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const request = useRef( 0 );
	const max = Number( config.max ) || 0;
	const chosen = new Set( selected.map( ( item ) => item.id ) );

	const run = ( query, nextPage = 1 ) => {
		const ticket = ++request.current;
		setLoading( true );
		setError( '' );
		search( config, query, nextPage )
			.then( ( response ) => {
				if ( ticket !== request.current ) {
					return;
				}
				const items = Array.isArray( response?.items ) ? response.items : [];
				setResults( ( current ) => ( nextPage > 1 ? [ ...current, ...items ] : items ) );
				setMore( !! response?.more );
				setPage( nextPage );
			} )
			.catch( ( failure ) => {
				if ( ticket === request.current ) {
					setError( failure?.message || __( 'Search failed. Try again.', 'custom-fields-meta-box' ) );
				}
			} )
			.finally( () => {
				if ( ticket === request.current ) {
					setLoading( false );
				}
			} );
	};

	const debouncedRun = useDebounce( run, 250 );

	useEffect( () => {
		run( '', 1 );
		// Load the first page once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const add = ( item ) => {
		const next = toggleItem( selected, item, max );
		if ( next === selected ) {
			return;
		}
		setSelected( next );
		/* translators: %s: item title */
		speak( sprintf( __( '%s linked.', 'custom-fields-meta-box' ), item.title ) );
	};

	const remove = ( item ) => {
		setSelected( selected.filter( ( other ) => other.id !== item.id ) );
		/* translators: %s: item title */
		speak( sprintf( __( '%s unlinked.', 'custom-fields-meta-box' ), item.title ) );
	};

	const move = ( index, step ) => setSelected( moveItem( selected, index, step ) );

	return (
		<div className="cfmb-connection__box">
			{ selected.map( ( item ) => (
				<input key={ item.id } type="hidden" name={ config.input } value={ item.id } />
			) ) }

			{ selected.length > 0 ? (
				<ol className="cfmb-connection__selected" aria-label={ __( 'Linked items', 'custom-fields-meta-box' ) }>
					{ selected.map( ( item, index ) => (
						<li key={ item.id } className="cfmb-connection__item">
							{ item.url ? (
								<a href={ item.url } className="cfmb-connection__link">
									<ItemText item={ item } />
								</a>
							) : (
								<ItemText item={ item } />
							) }
							<span className="cfmb-connection__actions">
								{ max !== 1 && (
									<>
										<Button
											size="small"
											icon="arrow-up-alt2"
											label={ __( 'Move up', 'custom-fields-meta-box' ) }
											disabled={ index === 0 }
											accessibleWhenDisabled
											onClick={ () => move( index, -1 ) }
										/>
										<Button
											size="small"
											icon="arrow-down-alt2"
											label={ __( 'Move down', 'custom-fields-meta-box' ) }
											disabled={ index === selected.length - 1 }
											accessibleWhenDisabled
											onClick={ () => move( index, 1 ) }
										/>
									</>
								) }
								<Button
									size="small"
									icon="no-alt"
									isDestructive
									label={ sprintf(
										/* translators: %s: item title */
										__( 'Unlink %s', 'custom-fields-meta-box' ),
										item.title
									) }
									onClick={ () => remove( item ) }
								/>
							</span>
							<ItemExtra item={ item } config={ config } index={ index } />
						</li>
					) ) }
				</ol>
			) : (
				<p className="cfmb-connection__empty description">{ __( 'Nothing linked yet. Search below to add items.', 'custom-fields-meta-box' ) }</p>
			) }

			<div className="cfmb-connection__search">
				<input
					type="search"
					className="cfmb-connection__input"
					placeholder={ PLACEHOLDERS[ config.kind ] || PLACEHOLDERS.post }
					aria-label={ PLACEHOLDERS[ config.kind ] || PLACEHOLDERS.post }
					value={ text }
					onChange={ ( event ) => {
						setText( event.target.value );
						debouncedRun( event.target.value, 1 );
					} }
				/>
				{ loading && <Spinner /> }
			</div>

			{ error && (
				<p className="cfmb-connection__error" role="alert">
					{ error }
				</p>
			) }

			{ max === 1 && selected.length === 1 && (
				<p className="cfmb-connection__hint description">{ __( 'Only one item can be linked. Picking another replaces it.', 'custom-fields-meta-box' ) }</p>
			) }

			<ul className="cfmb-connection__results">
				{ results.map( ( item ) => {
					const isChosen = chosen.has( item.id );
					return (
						<li key={ item.id }>
							<button
								type="button"
								className="cfmb-connection__result"
								aria-pressed={ isChosen }
								onClick={ () => ( isChosen ? remove( item ) : add( item ) ) }
							>
								<ItemText item={ item } />
								<span className="cfmb-connection__state">{ isChosen ? __( 'Linked', 'custom-fields-meta-box' ) : __( 'Link', 'custom-fields-meta-box' ) }</span>
							</button>
						</li>
					);
				} ) }
				{ ! loading && ! error && results.length === 0 && <li className="cfmb-connection__none">{ __( 'No matches.', 'custom-fields-meta-box' ) }</li> }
			</ul>

			{ more && (
				<Button variant="link" disabled={ loading } accessibleWhenDisabled onClick={ () => run( text, page + 1 ) }>
					{ __( 'Show more', 'custom-fields-meta-box' ) }
				</Button>
			) }
		</div>
	);
}
