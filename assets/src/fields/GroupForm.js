/**
 * One Field Group on an edit screen.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isVisible } from '../shared/conditions';
import Fields from './Fields';
import { FormContext } from './context';
import { validateField, validateTree } from './validation';
import { prepareAll, serializeAll, storesValue, structureOf } from './values';

export default function GroupForm( { config, input, registry, components } ) {
	const fields = useMemo( () => config.fields || [], [ config.fields ] );
	const [ values, setValues ] = useState( () => prepareAll( fields, config.values ) );
	const [ serverErrors, setServerErrors ] = useState( () => ( { ...( config.errors || {} ) } ) );
	const [ touched, setTouched ] = useState( () => new Set() );
	const [ reveal, setReveal ] = useState( null );

	// Keep the hidden input current at all times; the block editor reads it without a submit event.
	const firstWrite = useRef( true );
	useEffect( () => {
		input.value = JSON.stringify( serializeAll( fields, values ) );
		registry.changed();

		// Screens that only save what changed (WooCommerce variations, the
		// Customizer) watch change events, which setting .value never fires.
		if ( firstWrite.current ) {
			firstWrite.current = false;
			return;
		}
		// Field Block forms pass a plain object, not an input element.
		input.dispatchEvent?.( new window.Event( 'change', { bubbles: true } ) );
	}, [ fields, values, input, registry ] );

	const valuesRef = useRef( values );
	valuesRef.current = values;

	// The same group can show many times on one screen (menu items, widgets).
	const instance = config.instance || config.key;

	useEffect( () => {
		registry.register( instance, {
			title: config.title,
			objectRef: config.objectRef || '',
			snapshot() {
				return { key: config.key, title: config.title, objectRef: config.objectRef || '', fields, values: valuesRef.current };
			},
			// Top-level single values by field name, for Block Bindings edits made on the canvas.
			valueByName( name ) {
				const field = fields.find( ( item ) => item.name === name && storesValue( item ) && ! structureOf( item ) );
				return field ? { found: true, value: valuesRef.current[ field.key ] } : { found: false };
			},
			setByName( name, value ) {
				const field = fields.find( ( item ) => item.name === name && storesValue( item ) && ! structureOf( item ) );
				if ( field && String( valuesRef.current[ field.key ] ?? '' ) !== String( value ?? '' ) ) {
					setValues( ( current ) => ( { ...current, [ field.key ]: value } ) );
				}
			},
			invalid( enforceRequired ) {
				// Boxes waiting for their location rules to match are not saved, so they can't block saving.
				if ( input.closest?.( '.cfmb-box-waiting' ) ) {
					return [];
				}
				const current = valuesRef.current;
				return fields
					.filter( ( field ) => storesValue( field ) && isVisible( field, current ) )
					.flatMap( ( field ) => validateTree( field, current[ field.key ], enforceRequired, field.key ) );
			},
			reveal( enforceRequired ) {
				setReveal( { enforceRequired, at: Date.now() } );
			},
		} );
		return () => registry.unregister( instance );
	}, [ instance, config.title, config.objectRef, registry, fields, input ] );

	const setValue = useCallback( ( key, update ) => {
		setValues( ( current ) => ( { ...current, [ key ]: typeof update === 'function' ? update( current[ key ] ) : update } ) );
	}, [] );

	const touch = useCallback( ( path ) => {
		setTouched( ( current ) => ( current.has( path ) ? current : new Set( current ).add( path ) ) );
		setServerErrors( ( current ) => {
			if ( ! current[ path ] ) {
				return current;
			}
			const next = { ...current };
			delete next[ path ];
			return next;
		} );
	}, [] );

	const errorFor = useCallback(
		( path, field, value ) => {
			if ( serverErrors[ path ] ) {
				return serverErrors[ path ];
			}
			if ( reveal ) {
				return validateField( field, value, reveal.enforceRequired );
			}
			return touched.has( path ) ? validateField( field, value, true ) : null;
		},
		[ serverErrors, reveal, touched ]
	);

	const context = useMemo(
		() => ( {
			groupKey: config.key,
			// Element ids; a block form passes its own so two blocks of one type don't share ids.
			idBase: config.idBase || config.key,
			objectKind: config.objectKind,
			objectId: config.objectId,
			objectRef: config.objectRef || ( config.objectKind === 'option' ? `option:${ config.objectPage || 'options' }` : `${ config.objectKind }_${ config.objectId }` ),
			components,
			reveal,
			serverErrors,
			errorFor,
			touch,
		} ),
		[ config.key, config.idBase, config.objectKind, config.objectId, config.objectPage, config.objectRef, components, reveal, serverErrors, errorFor, touch ]
	);

	if ( ! fields.length ) {
		return <p className="cfmb-group__empty">{ __( 'This field group has no fields yet.', 'custom-fields-meta-box' ) }</p>;
	}

	return (
		<FormContext.Provider value={ context }>
			<div className="cfmb-group__fields">
				<Fields fields={ fields } values={ values } onChange={ setValue } path="" />
			</div>
		</FormContext.Provider>
	);
}
