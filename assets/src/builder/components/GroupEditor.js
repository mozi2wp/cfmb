/**
 * Edit one Field Group: title, fields, location and settings.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import config from '../config';
import { createGroup, getGroup, syncPull, updateGroup } from '../api';
import { snackbar, syncNoteSnackbar } from '../notices';
import { newGroupKey } from '../../shared/keys';
import { BuilderContext } from '../context';
import { allLists, ancestors, moveField, namesByKey } from '../tree';
import ConfirmModal from './ConfirmModal';
import FieldBlockSettings from './FieldBlockSettings';
import FieldList from './FieldList';
import GroupSettings from './GroupSettings';
import LocationEditor from './LocationEditor';

/**
 * Location to start a new group with. A link such as the empty state of an
 * options page can pass ?location=options_page:site_settings.
 *
 * @return {Array} Location rule sets.
 */
function initialLocation() {
	const wanted = new URL( window.location.href ).searchParams.get( 'location' ) || '';
	const [ param, ...rest ] = wanted.split( ':' );
	const value = rest.join( ':' );

	if ( param && value && config.locationRules.some( ( rule ) => rule.param === param ) ) {
		return [ [ { param, operator: '==', value } ] ];
	}

	return JSON.parse( JSON.stringify( config.defaultLocation ) );
}

function blankGroup() {
	return {
		key: newGroupKey(),
		title: '',
		active: true,
		order: 0,
		fields: [],
		location: initialLocation(),
		settings: { position: 'normal', label_placement: 'top', description: '', show_in_rest: false },
		source: 'db',
	};
}

/**
 * Block name the group was last saved with, if it was a Field Block.
 *
 * @param {string|null} saved Saved snapshot.
 * @return {string} Name.
 */
function savedBlockName( saved ) {
	try {
		const block = saved ? JSON.parse( saved ).settings?.block : null;
		return block?.enabled ? block.name || '' : '';
	} catch ( error ) {
		return '';
	}
}

// Only what the server stores, so the dirty check ignores the id it adds.
const snapshot = ( group ) =>
	JSON.stringify( {
		title: group.title,
		active: group.active,
		order: group.order,
		fields: group.fields,
		location: group.location,
		settings: group.settings,
	} );

export default function GroupEditor( { groupKey, navigate } ) {
	const isNew = groupKey === 'new';
	const [ initial ] = useState( () => ( isNew ? blankGroup() : null ) );
	const [ group, setGroup ] = useState( initial );
	const [ saved, setSaved ] = useState( () => ( initial ? snapshot( initial ) : null ) );
	const [ stored, setStored ] = useState( ! isNew );
	const [ loadError, setLoadError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ expanded, setExpanded ] = useState( () => new Set() );
	const [ leaving, setLeaving ] = useState( false );
	const [ renames, setRenames ] = useState( null );
	const titleRef = useRef();
	const dragRef = useRef( null );

	useEffect( () => {
		if ( isNew ) {
			titleRef.current?.focus();
			return;
		}
		getGroup( groupKey )
			.then( ( loaded ) => {
				setGroup( loaded );
				setSaved( snapshot( loaded ) );
			} )
			.catch( ( failure ) => setLoadError( failure.message || __( 'This field group could not be loaded.', 'cfmb' ) ) );
	}, [ groupKey, isNew ] );

	const readOnly = !! group && !! group.source && group.source !== 'db';
	const dirty = group !== null && ! readOnly && snapshot( group ) !== saved;
	const groupRef = useRef( group );
	groupRef.current = group;

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const patch = useCallback( ( change ) => setGroup( ( current ) => ( { ...current, ...change } ) ), [] );
	const setFields = useCallback(
		( change ) => setGroup( ( current ) => ( { ...current, fields: typeof change === 'function' ? change( current.fields ) : change } ) ),
		[]
	);
	const setLocation = useCallback(
		( change ) => setGroup( ( current ) => ( { ...current, location: typeof change === 'function' ? change( current.location ) : change } ) ),
		[]
	);

	const importToEdit = async () => {
		setSaving( true );
		setError( null );
		try {
			const result = await syncPull( [ { kind: 'group', key: group.key } ] );
			if ( result.errors?.length ) {
				throw new Error( result.errors[ 0 ].message );
			}
			const loaded = await getGroup( group.key );
			setGroup( loaded );
			setSaved( snapshot( loaded ) );
			snackbar( __( 'Imported. You can edit this field group here now.', 'cfmb' ) );
		} catch ( failure ) {
			setError( { message: failure.message || __( 'The field group could not be imported.', 'cfmb' ) } );
		} finally {
			setSaving( false );
		}
	};

	const save = async ( confirmed = false ) => {
		if ( saving || ! group || readOnly ) {
			return;
		}
		if ( ! group.title.trim() ) {
			setError( { message: __( 'Give the field group a title.', 'cfmb' ) } );
			titleRef.current?.focus();
			return;
		}

		// Other groups insert this one as a preset; renamed fields there lose sight of saved values.
		if ( ! confirmed && group.usedBy?.length && saved ) {
			const before = namesByKey( JSON.parse( saved ).fields );
			const after = namesByKey( group.fields );
			const changed = Object.keys( before )
				.filter( ( key ) => before[ key ] !== after[ key ] )
				.map( ( key ) => ( { from: before[ key ], to: after[ key ] || '' } ) );
			if ( changed.length ) {
				setRenames( changed );
				return;
			}
		}

		setSaving( true );
		setError( null );

		const sent = group;

		try {
			const result = stored ? await updateGroup( sent ) : await createGroup( sent );
			// Keep edits made while the request was in flight; they show as unsaved.
			setGroup( ( current ) => ( current === sent ? result : { ...current, key: result.key, id: result.id } ) );
			setSaved( snapshot( result ) );
			setStored( true );
			snackbar( __( 'Field group saved.', 'cfmb' ) );
			syncNoteSnackbar( result.syncNote );
			if ( isNew ) {
				navigate( result.key, { replace: true, keepState: true } );
			}
		} catch ( failure ) {
			const fieldKey = failure?.data?.field;
			setError( { message: failure.message || __( 'The field group could not be saved. Please try again.', 'cfmb' ), field: fieldKey } );
			if ( fieldKey ) {
				setExpanded( ( current ) => new Set( [ ...current, ...ancestors( sent.fields, fieldKey ), fieldKey ] ) );
				window.setTimeout( () => document.querySelector( `[data-field-key="${ fieldKey }"]` )?.scrollIntoView( { block: 'center' } ), 50 );
			}
		} finally {
			setSaving( false );
		}
	};

	// Ctrl/Cmd + S saves, like in the post editor.
	const saveRef = useRef( save );
	saveRef.current = save;
	useEffect( () => {
		const onKeyDown = ( event ) => {
			if ( ( event.metaKey || event.ctrlKey ) && event.key.toLowerCase() === 's' ) {
				event.preventDefault();
				saveRef.current();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [] );

	const back = () => ( dirty ? setLeaving( true ) : navigate( '' ) );

	const builder = useMemo(
		() => ( {
			groupKey: group?.key || '',
			dragRef,
			moveField: ( key, listId, index ) => {
				setFields( ( current ) => moveField( current, key, listId, index ) );
				setExpanded( ( current ) => new Set( [ ...current, ...( listId ? [ listId.split( ':' )[ 0 ] ] : [] ) ] ) );
			},
			lists: () => allLists( groupRef.current?.fields || [] ),
		} ),
		[ group?.key, setFields ]
	);

	if ( loadError ) {
		return (
			<div className="cfmb-editor">
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
				<Button variant="link" onClick={ () => navigate( '' ) }>
					{ __( 'Back to all field groups', 'cfmb' ) }
				</Button>
			</div>
		);
	}

	if ( ! group ) {
		return (
			<div className="cfmb-editor cfmb-editor--loading">
				<Spinner /> { __( 'Loading field group…', 'cfmb' ) }
			</div>
		);
	}

	return (
		<BuilderContext.Provider value={ builder }>
		<div className="cfmb-editor">
			<div className="cfmb-editor__bar">
				<Button variant="tertiary" icon="arrow-left-alt2" onClick={ back }>
					{ __( 'All field groups', 'cfmb' ) }
				</Button>
				<div className="cfmb-editor__status" aria-live="polite">
					{ dirty && <span className="cfmb-editor__dirty">{ __( 'Unsaved changes', 'cfmb' ) }</span> }
				</div>
				{ readOnly ? (
					<Button variant="primary" isBusy={ saving } aria-disabled={ saving } onClick={ importToEdit }>
						{ __( 'Import to edit', 'cfmb' ) }
					</Button>
				) : (
					<Button variant="primary" isBusy={ saving } aria-disabled={ saving } onClick={ () => save() }>
						{ saving ? __( 'Saving…', 'cfmb' ) : __( 'Save field group', 'cfmb' ) }
					</Button>
				) }
			</div>

			{ readOnly && (
				<Notice status="info" isDismissible={ false } className="cfmb-editor__source">
					{ group.source === 'code'
						? __( 'This field group is registered in code, so changes here can\'t be saved. Import it to edit it here; then remove it from your code.', 'cfmb' )
						: __( 'This field group comes from a sync file and is not in the database yet. Import it to edit it here.', 'cfmb' ) }
				</Notice>
			) }

			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) } className="cfmb-editor__error">
					{ error.message }
				</Notice>
			) }

			<div className="cfmb-editor__layout">
				<div className="cfmb-editor__main">
					<TextControl
						ref={ titleRef }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						className="cfmb-editor__title"
						label={ __( 'Field group title', 'cfmb' ) }
						placeholder={ __( 'For example: Product details', 'cfmb' ) }
						value={ group.title }
						onChange={ ( title ) => patch( { title } ) }
					/>

					<Card className="cfmb-editor__card">
						<CardHeader>
							<h2 className="cfmb-editor__card-title">{ __( 'Fields', 'cfmb' ) }</h2>
						</CardHeader>
						<CardBody>
							<FieldList
								fields={ group.fields }
								setFields={ setFields }
								expanded={ expanded }
								setExpanded={ setExpanded }
								errorKey={ error?.field }
							/>
						</CardBody>
					</Card>

					<Card className="cfmb-editor__card">
						<CardHeader>
							<h2 className="cfmb-editor__card-title">{ __( 'Location', 'cfmb' ) }</h2>
						</CardHeader>
						<CardBody>
							<LocationEditor location={ group.location } onChange={ setLocation } />
							{ group.settings?.block?.enabled && (
								<p className="cfmb-editor__hint">
									{ __( 'As a Field Block, this group always shows in its block. Rules here add it to other places as well.', 'cfmb' ) }
								</p>
							) }
						</CardBody>
					</Card>

					<Card className="cfmb-editor__card">
						<CardHeader>
							<h2 className="cfmb-editor__card-title">{ __( 'Field Block', 'cfmb' ) }</h2>
						</CardHeader>
						<CardBody>
							<FieldBlockSettings group={ group } onChange={ patch } savedName={ savedBlockName( saved ) } />
						</CardBody>
					</Card>
				</div>

				<div className="cfmb-editor__side">
					<Card className="cfmb-editor__card">
						<CardHeader>
							<h2 className="cfmb-editor__card-title">{ __( 'Settings', 'cfmb' ) }</h2>
						</CardHeader>
						<CardBody>
							<GroupSettings group={ group } onChange={ patch } />
						</CardBody>
					</Card>
				</div>
			</div>

			{ leaving && (
				<ConfirmModal
					title={ __( 'Leave without saving?', 'cfmb' ) }
					confirmLabel={ __( 'Leave', 'cfmb' ) }
					isDestructive
					onConfirm={ () => {
						setLeaving( false );
						navigate( '' );
					} }
					onCancel={ () => setLeaving( false ) }
				>
					<p>{ __( 'Your changes to this field group have not been saved.', 'cfmb' ) }</p>
				</ConfirmModal>
			) }

			{ renames && (
				<ConfirmModal
					title={ __( 'Rename fields used as a preset?', 'cfmb' ) }
					confirmLabel={ __( 'Save anyway', 'cfmb' ) }
					onConfirm={ () => {
						setRenames( null );
						save( true );
					} }
					onCancel={ () => setRenames( null ) }
				>
					<p>{ __( 'These field groups insert this one as a preset:', 'cfmb' ) }</p>
					<ul className="cfmb-renames">
						{ group.usedBy.map( ( user ) => (
							<li key={ user.key }>{ user.title }</li>
						) ) }
					</ul>
					<p>{ __( 'These fields were renamed or removed:', 'cfmb' ) }</p>
					<ul className="cfmb-renames">
						{ renames.map( ( change ) => (
							<li key={ change.from }>
								<code>{ change.from }</code> { change.to ? <>→ <code>{ change.to }</code></> : __( '(removed)', 'cfmb' ) }
							</li>
						) ) }
					</ul>
					<p>{ __( 'Values saved under the old names stay in the database, but those groups will no longer show them. Change the names back to see them again.', 'cfmb' ) }</p>
				</ConfirmModal>
			) }
		</div>
		</BuilderContext.Provider>
	);
}
