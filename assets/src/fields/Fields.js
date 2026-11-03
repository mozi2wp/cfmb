/**
 * A list of fields: a whole group, a Group field's set or one row.
 * Handles tabs, accordions and conditional logic at any depth.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isVisible } from '../shared/conditions';
import FieldShell from './FieldShell';
import Message from './types/Message';
import { domId, joinPath, useForm } from './context';
import { validateTree } from './validation';
import { groupedTypes } from './types';

/**
 * Split fields into sections. Fields before the first tab render above the tabs.
 *
 * @param {Array} fields Field definitions.
 * @return {{ before: Array, tabs: Array }} Sections.
 */
function splitTabs( fields ) {
	const before = [];
	const tabs = [];

	fields.forEach( ( field ) => {
		if ( field.type === 'tab' ) {
			tabs.push( { tab: field, fields: [] } );
		} else if ( tabs.length ) {
			tabs[ tabs.length - 1 ].fields.push( field );
		} else {
			before.push( field );
		}
	} );

	return { before, tabs };
}

/**
 * Group fields that follow an accordion into it, up to the next accordion.
 * An accordion marked as an end point only closes the one before it.
 *
 * @param {Array} fields Field definitions in one section.
 * @return {Array} Items: { field } or { accordion, fields }.
 */
function splitAccordions( fields ) {
	const items = [];
	let current = null;

	fields.forEach( ( field ) => {
		if ( field.type === 'accordion' ) {
			current = field.settings?.endpoint ? null : { accordion: field, fields: [] };
			if ( current ) {
				items.push( current );
			}
		} else if ( current ) {
			current.fields.push( field );
		} else {
			items.push( { field } );
		}
	} );

	return items;
}

function hasProblems( fields, values, visible, reveal, path ) {
	return fields.some(
		( field ) =>
			visible[ field.key ] &&
			validateTree( field, values[ field.key ], reveal.enforceRequired, joinPath( path, field.key ) ).length > 0
	);
}

function Accordion( { accordion, children, values, fields, visible, path } ) {
	const { idBase, reveal } = useForm();
	const [ open, setOpen ] = useState( !! accordion.settings?.open );
	const panelId = `${ domId( idBase, joinPath( path, accordion.key ) ) }__panel`;

	useEffect( () => {
		if ( reveal && hasProblems( fields, values, visible, reveal, path ) ) {
			setOpen( true );
		}
		// Only when a reveal is requested.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ reveal ] );

	return (
		<div className={ `cfmb-accordion${ open ? ' is-open' : '' }` } data-cfmb-field={ accordion.key }>
			<h3 className="cfmb-accordion__heading">
				<button type="button" className="cfmb-accordion__toggle" aria-expanded={ open } aria-controls={ panelId } onClick={ () => setOpen( ! open ) }>
					<span>{ accordion.label }</span>
					<span className="cfmb-accordion__icon" aria-hidden="true" />
				</button>
			</h3>
			{ accordion.instructions && (
				<p className="cfmb-accordion__instructions" dangerouslySetInnerHTML={ { __html: accordion.instructions } } />
			) }
			<div id={ panelId } className="cfmb-accordion__panel cfmb-fields" hidden={ ! open }>
				{ children }
			</div>
		</div>
	);
}

export default function Fields( { fields, values, onChange, path = '' } ) {
	const form = useForm();
	const { idBase, components, reveal } = form;
	const sections = useMemo( () => splitTabs( fields ), [ fields ] );
	const visible = useMemo( () => {
		const map = {};
		fields.forEach( ( field ) => ( map[ field.key ] = isVisible( field, values ) ) );
		return map;
	}, [ fields, values ] );
	const [ activeTab, setActiveTab ] = useState( 0 );
	const tabsId = domId( idBase, joinPath( path, 'tabs' ) );

	// Show the tab holding the first error when errors are revealed.
	useEffect( () => {
		if ( ! reveal ) {
			return;
		}
		const index = sections.tabs.findIndex( ( section ) => hasProblems( section.fields, values, visible, reveal, path ) );
		if ( index >= 0 ) {
			setActiveTab( index );
		}
		// Only when a reveal is requested.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ reveal ] );

	const renderField = ( field ) => {
		if ( ! visible[ field.key ] ) {
			return null;
		}

		if ( field.type === 'message' ) {
			return <Message key={ field.key } field={ field } />;
		}

		const fieldPath = joinPath( path, field.key );
		const Control = components[ field.type ];
		const id = domId( idBase, fieldPath );

		if ( ! Control ) {
			return (
				<div key={ field.key } className="cfmb-field cfmb-field--unknown">
					<p className="cfmb-field__error">
						{ field.type === 'preset'
							? __( 'This preset\'s field group no longer exists. Edit the field group to choose another one.', 'cfmb' )
							: __( 'This field type is not available. The plugin that adds it may be inactive.', 'cfmb' ) }
					</p>
				</div>
			);
		}

		return (
			<FieldShell
				key={ field.key }
				field={ field }
				inputId={ id }
				path={ fieldPath }
				error={ form.errorFor( fieldPath, field, values[ field.key ] ) }
				labelAsLegend={ groupedTypes.has( field.type ) }
			>
				{ ( { describedBy } ) => (
					<Control
						field={ field }
						id={ id }
						path={ fieldPath }
						value={ values[ field.key ] }
						describedBy={ describedBy }
						objectId={ form.objectId }
						onChange={ ( value ) => {
							onChange( field.key, value );
							form.touch( fieldPath );
						} }
					/>
				) }
			</FieldShell>
		);
	};

	const renderList = ( list ) =>
		splitAccordions( list ).map( ( item ) =>
			item.accordion ? (
				visible[ item.accordion.key ] && (
					<Accordion
						key={ item.accordion.key }
						accordion={ item.accordion }
						fields={ item.fields }
						values={ values }
						visible={ visible }
						path={ path }
					>
						{ item.fields.map( renderField ) }
					</Accordion>
				)
			) : (
				renderField( item.field )
			)
		);

	const visibleTabs = sections.tabs.map( ( section, index ) => ( { ...section, index } ) ).filter( ( section ) => visible[ section.tab.key ] );
	const currentTab = visibleTabs.find( ( section ) => section.index === activeTab ) || visibleTabs[ 0 ];
	const placement = sections.tabs[ 0 ]?.tab.settings?.placement === 'left' ? 'left' : 'top';

	const onTabKeyDown = ( event, position ) => {
		let step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ event.key ];
		if ( ! step ) {
			return;
		}
		event.preventDefault();
		if ( document.dir === 'rtl' && ( event.key === 'ArrowLeft' || event.key === 'ArrowRight' ) ) {
			step = -step;
		}
		const next = visibleTabs[ ( position + step + visibleTabs.length ) % visibleTabs.length ];
		setActiveTab( next.index );
		document.getElementById( `${ tabsId }-${ next.index }` )?.focus();
	};

	return (
		<>
			{ sections.before.length > 0 && <div className="cfmb-fields">{ renderList( sections.before ) }</div> }
			{ visibleTabs.length > 0 && (
				<div className={ `cfmb-tabs cfmb-tabs--${ placement }` }>
					<div className="cfmb-tabs__list" role="tablist" aria-orientation={ placement === 'left' ? 'vertical' : 'horizontal' }>
						{ visibleTabs.map( ( section, position ) => {
							const selected = section === currentTab;
							return (
								<button
									key={ section.tab.key }
									id={ `${ tabsId }-${ section.index }` }
									type="button"
									role="tab"
									aria-selected={ selected }
									aria-controls={ `${ tabsId }-panel-${ section.index }` }
									tabIndex={ selected ? 0 : -1 }
									className={ `cfmb-tabs__tab${ selected ? ' is-active' : '' }` }
									onClick={ () => setActiveTab( section.index ) }
									onKeyDown={ ( event ) => onTabKeyDown( event, position ) }
								>
									{ section.tab.label }
								</button>
							);
						} ) }
					</div>
					{ visibleTabs.map( ( section ) => (
						<div
							key={ section.tab.key }
							id={ `${ tabsId }-panel-${ section.index }` }
							role="tabpanel"
							aria-labelledby={ `${ tabsId }-${ section.index }` }
							className="cfmb-tabs__panel cfmb-fields"
							hidden={ section !== currentTab }
						>
							{ renderList( section.fields ) }
						</div>
					) ) }
				</div>
			) }
		</>
	);
}
