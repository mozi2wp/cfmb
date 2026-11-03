/**
 * Location rules: which screens a group shows on.
 */
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import config, { rulesByParam } from '../config';
import { ruleChoices } from '../api';
import { uiIcon } from '../../shared/ui-icons';

const common = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

function RuleValue( { rule, onChange } ) {
	const [ state, setState ] = useState( {
		loading: true,
		choices: [],
		failed: false,
	} );

	useEffect( () => {
		let current = true;
		setState( { loading: true, choices: [], failed: false } );
		ruleChoices( rule.param )
			.then(
				( choices ) =>
					current &&
					setState( { loading: false, choices, failed: false } )
			)
			.catch(
				() =>
					current &&
					setState( { loading: false, choices: [], failed: true } )
			);
		return () => {
			current = false;
		};
	}, [ rule.param ] );

	// Pick the first choice for a fresh rule so it is never saved empty by accident.
	useEffect( () => {
		if ( ! state.loading && ! rule.value && state.choices.length ) {
			onChange( state.choices[ 0 ].value );
		}
		// Only when choices arrive.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ state.loading ] );

	if ( state.loading ) {
		return (
			<span className="cfmb-rules__loading">
				<Spinner />
			</span>
		);
	}

	if ( state.failed || ! state.choices.length ) {
		return (
			<TextControl
				{ ...common }
				label={ __( 'Value', 'custom-fields-meta-box' ) }
				hideLabelFromVision
				value={ rule.value }
				onChange={ onChange }
			/>
		);
	}

	const known = state.choices.some(
		( choice ) => choice.value === rule.value
	);

	return (
		<SelectControl
			{ ...common }
			label={ __( 'Value', 'custom-fields-meta-box' ) }
			hideLabelFromVision
			value={ rule.value }
			options={ [
				...( known || ! rule.value
					? []
					: [ { value: rule.value, label: rule.value } ] ),
				...state.choices,
			] }
			onChange={ onChange }
		/>
	);
}

function ParamSelect( { value, onChange } ) {
	const categories = [];
	config.locationRules.forEach( ( rule ) => {
		let category = categories.find(
			( item ) => item.label === rule.category
		);
		if ( ! category ) {
			category = { label: rule.category, rules: [] };
			categories.push( category );
		}
		category.rules.push( rule );
	} );

	return (
		<SelectControl
			{ ...common }
			label={ __( 'Rule', 'custom-fields-meta-box' ) }
			hideLabelFromVision
			value={ value }
			onChange={ onChange }
		>
			{ categories.map( ( category ) => (
				<optgroup key={ category.label } label={ category.label }>
					{ category.rules.map( ( rule ) => (
						<option key={ rule.param } value={ rule.param }>
							{ rule.label }
						</option>
					) ) }
				</optgroup>
			) ) }
		</SelectControl>
	);
}

export default function LocationEditor( { location, onChange } ) {
	const sets = Array.isArray( location ) ? location : [];
	const newRule = () => ( { param: 'post_type', operator: '==', value: '' } );
	// Updaters get the latest list, so rules that change together never overwrite each other.
	const update = ( change ) =>
		onChange( ( previous ) =>
			change( previous || [] ).filter( ( set ) => set.length )
		);
	const setRule = ( setIndex, ruleIndex, patch ) =>
		update( ( list ) =>
			list.map( ( set, s ) =>
				s === setIndex
					? set.map( ( rule, r ) =>
							r === ruleIndex ? { ...rule, ...patch } : rule
					  )
					: set
			)
		);

	return (
		<div className="cfmb-rules cfmb-rules--location">
			{ ! sets.length && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This group has no location rules, so it does not show on any screen yet.',
						'custom-fields-meta-box'
					) }
				</Notice>
			) }
			{ sets.map( ( set, setIndex ) => (
				<div className="cfmb-rules__set" key={ setIndex }>
					<p className="cfmb-rules__set-label">
						{ setIndex === 0
							? __(
									'Show this group when',
									'custom-fields-meta-box'
							  )
							: __( 'or when', 'custom-fields-meta-box' ) }
					</p>
					{ set.map( ( rule, ruleIndex ) => {
						const type = rulesByParam[ rule.param ];
						return (
							<div className="cfmb-rules__rule" key={ ruleIndex }>
								{ ruleIndex > 0 && (
									<span className="cfmb-rules__joiner">
										{ __(
											'and',
											'custom-fields-meta-box'
										) }
									</span>
								) }
								<ParamSelect
									value={ rule.param }
									onChange={ ( param ) =>
										setRule( setIndex, ruleIndex, {
											param,
											operator: '==',
											value: '',
										} )
									}
								/>
								<SelectControl
									{ ...common }
									label={ __(
										'Comparison',
										'custom-fields-meta-box'
									) }
									hideLabelFromVision
									value={ rule.operator }
									options={ ( type?.operators || [] ).map(
										( item ) => ( {
											value: item.value,
											label: item.label,
										} )
									) }
									onChange={ ( operator ) =>
										setRule( setIndex, ruleIndex, {
											operator,
										} )
									}
								/>
								<RuleValue
									key={ rule.param }
									rule={ rule }
									onChange={ ( value ) =>
										setRule( setIndex, ruleIndex, {
											value,
										} )
									}
								/>
								<Button
									icon={ uiIcon( 'no-alt' ) }
									label={ __(
										'Remove rule',
										'custom-fields-meta-box'
									) }
									size="compact"
									onClick={ () =>
										update( ( list ) =>
											list.map( ( item, s ) =>
												s === setIndex
													? item.filter(
															( _, r ) =>
																r !== ruleIndex
													  )
													: item
											)
										)
									}
								/>
							</div>
						);
					} ) }
					<Button
						variant="secondary"
						size="compact"
						onClick={ () =>
							update( ( list ) =>
								list.map( ( item, s ) =>
									s === setIndex
										? [ ...item, newRule() ]
										: item
								)
							)
						}
					>
						{ __( 'Add "and" rule', 'custom-fields-meta-box' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="link"
				onClick={ () =>
					update( ( list ) => [ ...list, [ newRule() ] ] )
				}
			>
				{ sets.length
					? __( 'Add "or" group', 'custom-fields-meta-box' )
					: __( 'Add a rule', 'custom-fields-meta-box' ) }
			</Button>
		</div>
	);
}
