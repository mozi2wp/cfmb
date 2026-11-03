/**
 * Search-and-pick control for Post, Page link, User and Taxonomy (search
 * box appearances). Results come from cfmb/v1/search, which applies the
 * field's own post type, role or taxonomy settings.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { useForm } from '../context';
import { search } from '../api';
import { uiIcon } from '../../shared/ui-icons';

const STATUS_LABELS = {
	draft: __( 'Draft', 'custom-fields-meta-box' ),
	pending: __( 'Pending', 'custom-fields-meta-box' ),
	private: __( 'Private', 'custom-fields-meta-box' ),
	future: __( 'Scheduled', 'custom-fields-meta-box' ),
};

export function ItemLabel( { item } ) {
	return (
		<>
			{ item.avatar && (
				<img
					className="cfmb-picker__avatar"
					src={ item.avatar }
					alt=""
				/>
			) }
			<span className="cfmb-picker__title">
				{ item.title || __( '(no title)', 'custom-fields-meta-box' ) }
			</span>
			{ item.typeLabel && (
				<span className="cfmb-picker__meta">{ item.typeLabel }</span>
			) }
			{ STATUS_LABELS[ item.status ] && (
				<span className="cfmb-picker__meta">
					{ STATUS_LABELS[ item.status ] }
				</span>
			) }
		</>
	);
}

/**
 * Search results with paging, for a field.
 *
 * @param {Object} field Field definition.
 * @return {Object} State and loaders.
 */
export function useSearch( field ) {
	const { groupKey, objectRef } = useForm();
	const [ state, setState ] = useState( {
		items: [],
		more: false,
		loading: false,
		error: '',
		page: 1,
		query: null,
	} );
	const request = useRef( 0 );

	// Stable, so debounced callers keep their pending call across renders.
	const run = useCallback(
		( query, page = 1, extra = {} ) => {
			const current = ++request.current;
			setState( ( previous ) => ( {
				...previous,
				loading: true,
				error: '',
				query,
			} ) );
			// The object lets people without "list users" pick users for things they can edit.
			search( {
				group: groupKey,
				field: field.key,
				object: objectRef,
				search: query,
				page,
				...extra,
			} )
				.then( ( result ) => {
					if ( current !== request.current ) {
						return;
					}
					setState( ( previous ) => ( {
						items:
							page > 1
								? [ ...previous.items, ...result.items ]
								: result.items,
						more: result.more,
						loading: false,
						error: '',
						page,
						query,
					} ) );
				} )
				.catch( ( error ) => {
					if ( current === request.current ) {
						setState( ( previous ) => ( {
							...previous,
							loading: false,
							error:
								error.message ||
								__(
									'Search failed. Try again.',
									'custom-fields-meta-box'
								),
						} ) );
					}
				} );
		},
		[ groupKey, objectRef, field.key ]
	);

	return { ...state, run };
}

export default function AsyncPicker( {
	field,
	id,
	value,
	onChange,
	describedBy,
	multiple,
} ) {
	const selected = multiple
		? Array.isArray( value )
			? value
			: []
		: value
		? [ value ]
		: [];
	const [ text, setText ] = useState( '' );
	const [ open, setOpen ] = useState( false );
	const [ active, setActive ] = useState( -1 );
	const results = useSearch( field );
	const debouncedRun = useDebounce( results.run, 250 );
	const listId = `${ id }-results`;
	const wrapper = useRef();

	useEffect( () => {
		if ( open && results.query === null ) {
			results.run( '' );
		}
		// Load the first page once, when the list first opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open ] );

	useEffect( () => {
		const close = ( event ) => {
			if (
				wrapper.current &&
				! wrapper.current.contains( event.target )
			) {
				setOpen( false );
			}
		};
		document.addEventListener( 'mousedown', close );
		return () => document.removeEventListener( 'mousedown', close );
	}, [] );

	const chosenIds = new Set( selected.map( ( item ) => item.id ) );
	const options = results.items.filter(
		( item ) => ! chosenIds.has( item.id )
	);

	const pick = ( item ) => {
		if ( multiple ) {
			onChange( [ ...selected, item ] );
		} else {
			onChange( item );
			setOpen( false );
		}
		setText( '' );
		setActive( -1 );
		/* translators: %s: chosen item title */
		speak(
			sprintf( /* translators: %s: item title */ __( '%s chosen.', 'custom-fields-meta-box' ), item.title )
		);
	};

	const removeItem = ( item ) => {
		onChange(
			multiple
				? selected.filter( ( other ) => other.id !== item.id )
				: null
		);
		/* translators: %s: removed item title */
		speak(
			sprintf( /* translators: %s: item title */ __( '%s removed.', 'custom-fields-meta-box' ), item.title )
		);
	};

	const moveItem = ( index, step ) => {
		const target = index + step;
		if ( target < 0 || target >= selected.length ) {
			return;
		}
		const next = [ ...selected ];
		const [ item ] = next.splice( index, 1 );
		next.splice( target, 0, item );
		onChange( next );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			setOpen( true );
			setActive( ( current ) =>
				Math.min( options.length - 1, current + 1 )
			);
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			setActive( ( current ) => Math.max( 0, current - 1 ) );
		} else if ( event.key === 'Enter' && open && options[ active ] ) {
			event.preventDefault();
			pick( options[ active ] );
		} else if ( event.key === 'Escape' && open ) {
			event.preventDefault();
			setOpen( false );
		}
	};

	const showSearch = multiple || ! selected.length;

	return (
		<div className="cfmb-picker" ref={ wrapper }>
			{ selected.length > 0 && (
				<ul className="cfmb-picker__selected">
					{ selected.map( ( item, index ) => (
						<li key={ item.id } className="cfmb-picker__item">
							<ItemLabel item={ item } />
							<span className="cfmb-picker__item-actions">
								{ multiple && selected.length > 1 && (
									<>
										<Button
											size="small"
											icon={ uiIcon( 'arrow-up-alt2' ) }
											label={ __(
												'Move up',
												'custom-fields-meta-box'
											) }
											disabled={ index === 0 }
											onClick={ () =>
												moveItem( index, -1 )
											}
										/>
										<Button
											size="small"
											icon={ uiIcon( 'arrow-down-alt2' ) }
											label={ __(
												'Move down',
												'custom-fields-meta-box'
											) }
											disabled={
												index === selected.length - 1
											}
											onClick={ () =>
												moveItem( index, 1 )
											}
										/>
									</>
								) }
								<Button
									size="small"
									icon={ uiIcon( 'no-alt' ) }
									label={
										/* translators: %s: item title */
										sprintf(
											/* translators: %s: item title */ __(
												'Remove %s',
												'custom-fields-meta-box'
											),
											item.title
										)
									}
									onClick={ () => removeItem( item ) }
								/>
							</span>
						</li>
					) ) }
				</ul>
			) }
			{ showSearch && (
				<div className="cfmb-picker__search">
					<input
						id={ id }
						className="cfmb-input"
						type="search"
						role="combobox"
						aria-expanded={ open }
						aria-controls={ listId }
						aria-autocomplete="list"
						aria-activedescendant={
							open && options[ active ]
								? `${ listId }-${ options[ active ].id }`
								: undefined
						}
						aria-describedby={ describedBy }
						aria-required={ field.required || undefined }
						placeholder={ __(
							'Search…',
							'custom-fields-meta-box'
						) }
						value={ text }
						onFocus={ () => setOpen( true ) }
						onKeyDown={ onKeyDown }
						onChange={ ( event ) => {
							setText( event.target.value );
							setOpen( true );
							setActive( -1 );
							debouncedRun( event.target.value );
						} }
					/>
					{ open && (
						<ul
							className="cfmb-picker__results"
							id={ listId }
							role="listbox"
							aria-label={ __(
								'Search results',
								'custom-fields-meta-box'
							) }
						>
							{ options.map( ( item, index ) => (
								// eslint-disable-next-line jsx-a11y/click-events-have-key-events -- Arrow keys and Enter are handled on the combobox input.
								<li
									key={ item.id }
									id={ `${ listId }-${ item.id }` }
									role="option"
									aria-selected={ index === active }
									className={ `cfmb-picker__option${
										index === active ? ' is-active' : ''
									}` }
									onMouseDown={ ( event ) =>
										event.preventDefault()
									}
									onClick={ () => pick( item ) }
								>
									<ItemLabel item={ item } />
								</li>
							) ) }
							{ results.loading && (
								<li
									className="cfmb-picker__note"
									role="presentation"
								>
									<Spinner />
								</li>
							) }
							{ ! results.loading &&
								! options.length &&
								! results.error && (
									<li
										className="cfmb-picker__note"
										role="presentation"
									>
										{ __(
											'Nothing found.',
											'custom-fields-meta-box'
										) }
									</li>
								) }
							{ results.error && (
								<li
									className="cfmb-picker__note cfmb-field__error"
									role="presentation"
								>
									{ results.error }
								</li>
							) }
							{ results.more && ! results.loading && (
								<li
									className="cfmb-picker__note"
									role="presentation"
								>
									<Button
										variant="link"
										onMouseDown={ ( event ) =>
											event.preventDefault()
										}
										onClick={ () =>
											results.run(
												results.query || '',
												results.page + 1
											)
										}
									>
										{ __(
											'Show more',
											'custom-fields-meta-box'
										) }
									</Button>
								</li>
							) }
						</ul>
					) }
				</div>
			) }
		</div>
	);
}
