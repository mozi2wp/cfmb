/**
 * Conditional logic: show a field only when other fields have certain values.
 */
import { Button, Notice, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import config, { storesValue } from '../config';

const common = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

function ValueControl( { target, rule, onChange } ) {
	const label = __( 'Value', 'custom-fields-meta-box' );

	if ( target?.type === 'toggle' ) {
		return (
			<SelectControl
				{ ...common }
				label={ label }
				hideLabelFromVision
				value={ rule.value || '1' }
				options={ [
					{ value: '1', label: __( 'On', 'custom-fields-meta-box' ) },
					{ value: '0', label: __( 'Off', 'custom-fields-meta-box' ) },
				] }
				onChange={ onChange }
			/>
		);
	}

	const choices = target?.settings?.choices;
	if ( Array.isArray( choices ) && choices.length ) {
		return (
			<SelectControl
				{ ...common }
				label={ label }
				hideLabelFromVision
				value={ rule.value }
				options={ [ { value: '', label: __( '— Choose —', 'custom-fields-meta-box' ) }, ...choices ] }
				onChange={ onChange }
			/>
		);
	}

	return <TextControl { ...common } label={ label } hideLabelFromVision value={ rule.value } onChange={ onChange } />;
}

export default function ConditionsEditor( { field, siblings, onChange } ) {
	const targets = siblings.filter( ( item ) => item.key !== field.key && storesValue( item.type ) );
	const sets = field.conditions || [];

	if ( ! targets.length ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __( 'Add another field to this group first. Conditions compare against other fields.', 'custom-fields-meta-box' ) }
			</Notice>
		);
	}

	const newRule = () => ( { field: targets[ 0 ].key, operator: '==', value: '' } );
	// Updaters get the latest list, so rules that change together never overwrite each other.
	const update = ( change ) => onChange( ( previous ) => change( previous || [] ).filter( ( set ) => set.length ) );
	const setRule = ( setIndex, ruleIndex, patch ) =>
		update( ( list ) => list.map( ( set, s ) => ( s === setIndex ? set.map( ( rule, r ) => ( r === ruleIndex ? { ...rule, ...patch } : rule ) ) : set ) ) );

	return (
		<div className="cfmb-rules">
			{ sets.map( ( set, setIndex ) => (
				<div className="cfmb-rules__set" key={ setIndex }>
					<p className="cfmb-rules__set-label">{ setIndex === 0 ? __( 'Show this field when', 'custom-fields-meta-box' ) : __( 'or when', 'custom-fields-meta-box' ) }</p>
					{ set.map( ( rule, ruleIndex ) => {
						const target = targets.find( ( item ) => item.key === rule.field );
						const operator = config.conditionOperators.find( ( item ) => item.value === rule.operator );
						return (
							<div className="cfmb-rules__rule" key={ ruleIndex }>
								{ ruleIndex > 0 && <span className="cfmb-rules__joiner">{ __( 'and', 'custom-fields-meta-box' ) }</span> }
								<SelectControl
									{ ...common }
									label={ __( 'Field', 'custom-fields-meta-box' ) }
									hideLabelFromVision
									value={ rule.field }
									options={ [
										...( target ? [] : [ { value: rule.field, label: __( '(removed field)', 'custom-fields-meta-box' ) } ] ),
										...targets.map( ( item ) => ( { value: item.key, label: item.label || item.name || __( '(no label)', 'custom-fields-meta-box' ) } ) ),
									] }
									onChange={ ( next ) => setRule( setIndex, ruleIndex, { field: next, value: '' } ) }
								/>
								<SelectControl
									{ ...common }
									label={ __( 'Comparison', 'custom-fields-meta-box' ) }
									hideLabelFromVision
									value={ rule.operator }
									options={ config.conditionOperators.map( ( item ) => ( { value: item.value, label: item.label } ) ) }
									onChange={ ( next ) => setRule( setIndex, ruleIndex, { operator: next } ) }
								/>
								{ operator?.takesValue !== false ? (
									<ValueControl target={ target } rule={ rule } onChange={ ( next ) => setRule( setIndex, ruleIndex, { value: next } ) } />
								) : (
									<span className="cfmb-rules__spacer" />
								) }
								<Button
									icon="no-alt"
									label={ __( 'Remove condition', 'custom-fields-meta-box' ) }
									size="compact"
									onClick={ () => update( ( list ) => list.map( ( item, s ) => ( s === setIndex ? item.filter( ( _, r ) => r !== ruleIndex ) : item ) ) ) }
								/>
							</div>
						);
					} ) }
					<Button variant="secondary" size="compact" onClick={ () => update( ( list ) => list.map( ( item, s ) => ( s === setIndex ? [ ...item, newRule() ] : item ) ) ) }>
						{ __( 'Add "and" condition', 'custom-fields-meta-box' ) }
					</Button>
				</div>
			) ) }
			<Button variant="link" onClick={ () => update( ( list ) => [ ...list, [ newRule() ] ] ) }>
				{ __( 'Add "or" group', 'custom-fields-meta-box' ) }
			</Button>
		</div>
	);
}
