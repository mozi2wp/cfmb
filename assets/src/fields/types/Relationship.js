/**
 * Relationship field: search on the left, the ordered choice on the right.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { ItemLabel, useSearch } from './AsyncPicker';
import { ordered } from './TermChoices';
import { useForm } from '../context';
import { search } from '../api';
import { uiIcon } from '../../shared/ui-icons';

export default function Relationship( {
	field,
	id,
	value,
	onChange,
	describedBy,
} ) {
	const settings = field.settings || {};
	const filters = Array.isArray( settings.filters )
		? settings.filters
		: [ 'search', 'post_type' ];
	const selected = Array.isArray( value ) ? value : [];
	const max = Number( settings.max ) || 0;
	const results = useSearch( field );
	const [ text, setText ] = useState( '' );
	const [ postType, setPostType ] = useState( '' );
	const [ term, setTerm ] = useState( '' );
	const [ termFilters, setTermFilters ] = useState( [] );
	const { groupKey } = useForm();
	const debouncedRun = useDebounce( results.run, 250 );
	const chosenIds = new Set( selected.map( ( item ) => item.id ) );
	const full = max > 0 && selected.length >= max;
	const typeOptions = (
		Array.isArray( settings.post_type ) && settings.post_type.length
			? settings.post_type
			: Object.keys( window.cfmbFields?.postTypes || {} )
	).filter( Boolean );

	useEffect( () => {
		results.run( '', 1 );
		if ( filters.includes( 'taxonomy' ) ) {
			search( { group: groupKey, field: field.key, filters: true } )
				.then( ( list ) =>
					setTermFilters( Array.isArray( list ) ? list : [] )
				)
				.catch( () => setTermFilters( [] ) );
		}
		// Load the first page and the filter terms once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const add = ( item ) => {
		if ( full || chosenIds.has( item.id ) ) {
			return;
		}
		onChange( [ ...selected, item ] );
		/* translators: %s: post title */
		speak(
			sprintf( /* translators: %s: item title */ __( '%s added.', 'custom-fields-meta-box' ), item.title )
		);
	};

	const remove = ( item ) => {
		onChange( selected.filter( ( other ) => other.id !== item.id ) );
		/* translators: %s: post title */
		speak(
			sprintf( /* translators: %s: item title */ __( '%s removed.', 'custom-fields-meta-box' ), item.title )
		);
	};

	const move = ( index, step ) => {
		const target = index + step;
		if ( target < 0 || target >= selected.length ) {
			return;
		}
		const next = [ ...selected ];
		const [ item ] = next.splice( index, 1 );
		next.splice( target, 0, item );
		onChange( next );
	};

	return (
		<div className="cfmb-relationship" aria-describedby={ describedBy }>
			<div className="cfmb-relationship__pane">
				<div className="cfmb-relationship__filters">
					{ filters.includes( 'search' ) && (
						<input
							id={ id }
							className="cfmb-input"
							type="search"
							placeholder={ __(
								'Search…',
								'custom-fields-meta-box'
							) }
							aria-label={ __(
								'Search posts',
								'custom-fields-meta-box'
							) }
							value={ text }
							onChange={ ( event ) => {
								setText( event.target.value );
								debouncedRun( event.target.value, 1, {
									post_type: postType,
									term,
								} );
							} }
						/>
					) }
					{ filters.includes( 'post_type' ) &&
						typeOptions.length > 1 && (
							<select
								className="cfmb-input"
								aria-label={ __(
									'Post type',
									'custom-fields-meta-box'
								) }
								value={ postType }
								onChange={ ( event ) => {
									setPostType( event.target.value );
									results.run( text, 1, {
										post_type: event.target.value,
										term,
									} );
								} }
							>
								<option value="">
									{ __(
										'All types',
										'custom-fields-meta-box'
									) }
								</option>
								{ typeOptions.map( ( type ) => (
									<option key={ type } value={ type }>
										{ window.cfmbFields?.postTypes?.[
											type
										] || type }
									</option>
								) ) }
							</select>
						) }
					{ termFilters.length > 0 && (
						<select
							className="cfmb-input"
							aria-label={ __(
								'Category or tag',
								'custom-fields-meta-box'
							) }
							value={ term }
							onChange={ ( event ) => {
								setTerm( event.target.value );
								results.run( text, 1, {
									post_type: postType,
									term: event.target.value,
								} );
							} }
						>
							<option value="">
								{ __(
									'Any category or tag',
									'custom-fields-meta-box'
								) }
							</option>
							{ termFilters.map( ( filter ) => (
								<optgroup
									key={ filter.taxonomy }
									label={ filter.label }
								>
									{ ordered( filter.terms ).map( ( item ) => (
										<option
											key={ item.id }
											value={ `${ filter.taxonomy }:${ item.id }` }
										>
											{ `${ '— '.repeat( item.depth ) }${
												item.title
											}` }
										</option>
									) ) }
								</optgroup>
							) ) }
						</select>
					) }
				</div>
				<ul
					className="cfmb-relationship__list"
					aria-label={ __(
						'Posts to choose from',
						'custom-fields-meta-box'
					) }
				>
					{ results.items.map( ( item ) => (
						<li key={ item.id }>
							<button
								type="button"
								className="cfmb-relationship__choice"
								disabled={ chosenIds.has( item.id ) || full }
								onClick={ () => add( item ) }
							>
								<ItemLabel item={ item } />
								<span className="screen-reader-text">
									{ __( '(add)', 'custom-fields-meta-box' ) }
								</span>
							</button>
						</li>
					) ) }
					{ results.loading && (
						<li className="cfmb-picker__note">
							<Spinner />
						</li>
					) }
					{ ! results.loading && ! results.items.length && (
						<li className="cfmb-picker__note">
							{ results.error ||
								__(
									'Nothing found.',
									'custom-fields-meta-box'
								) }
						</li>
					) }
					{ results.more && ! results.loading && (
						<li className="cfmb-picker__note">
							<Button
								variant="link"
								onClick={ () =>
									results.run(
										results.query || '',
										results.page + 1,
										{ post_type: postType, term }
									)
								}
							>
								{ __( 'Show more', 'custom-fields-meta-box' ) }
							</Button>
						</li>
					) }
				</ul>
			</div>
			<div className="cfmb-relationship__pane">
				<p className="cfmb-relationship__heading">
					{ max > 0
						? sprintf(
								/* translators: 1: number chosen, 2: maximum */
								__(
									'Chosen: %1$d of %2$d',
									'custom-fields-meta-box'
								),
								selected.length,
								max
						  )
						: sprintf(
								/* translators: %d: number chosen */
								__( 'Chosen: %d', 'custom-fields-meta-box' ),
								selected.length
						  ) }
				</p>
				<ul className="cfmb-relationship__list cfmb-relationship__list--chosen">
					{ selected.map( ( item, index ) => (
						<li key={ item.id } className="cfmb-picker__item">
							<ItemLabel item={ item } />
							<span className="cfmb-picker__item-actions">
								<Button
									size="small"
									icon={ uiIcon( 'arrow-up-alt2' ) }
									label={ __(
										'Move up',
										'custom-fields-meta-box'
									) }
									disabled={ index === 0 }
									onClick={ () => move( index, -1 ) }
								/>
								<Button
									size="small"
									icon={ uiIcon( 'arrow-down-alt2' ) }
									label={ __(
										'Move down',
										'custom-fields-meta-box'
									) }
									disabled={ index === selected.length - 1 }
									onClick={ () => move( index, 1 ) }
								/>
								<Button
									size="small"
									icon={ uiIcon( 'no-alt' ) }
									label={
										/* translators: %s: post title */
										sprintf(
											/* translators: %s: item title */ __(
												'Remove %s',
												'custom-fields-meta-box'
											),
											item.title
										)
									}
									onClick={ () => remove( item ) }
								/>
							</span>
						</li>
					) ) }
					{ ! selected.length && (
						<li className="cfmb-picker__note">
							{ __(
								'Pick posts from the list.',
								'custom-fields-meta-box'
							) }
						</li>
					) }
				</ul>
			</div>
		</div>
	);
}
