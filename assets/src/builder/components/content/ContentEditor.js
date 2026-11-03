/**
 * Edit one post type, taxonomy, options page or Connection.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Notice,
	PanelBody,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import config from '../../config';
import { createContent, getContent, syncPull, updateContent } from '../../api';
import { snackbar, syncNoteSnackbar } from '../../notices';
import ConfirmModal from '../ConfirmModal';
import ContentSettingControl from './ContentSettingControl';
import { suggestName } from './labels';
import { uiIcon } from '../../../shared/ui-icons';

const common = { __nextHasNoMarginBottom: true };

const SECTIONS = {
	labels: __( 'Labels', 'custom-fields-meta-box' ),
	visibility: __( 'Visibility', 'custom-fields-meta-box' ),
	urls: __( 'URLs', 'custom-fields-meta-box' ),
	terms: __( 'Terms', 'custom-fields-meta-box' ),
	permissions: __( 'Permissions', 'custom-fields-meta-box' ),
	rest: __( 'REST API', 'custom-fields-meta-box' ),
	menu: __( 'Menu', 'custom-fields-meta-box' ),
	display: __( 'Display', 'custom-fields-meta-box' ),
};

const randomHex = () => {
	const buffer = new Uint8Array( 5 );
	window.crypto.getRandomValues( buffer );
	return Array.from( buffer, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
};

const blank = ( kind ) => ( {
	...JSON.parse( JSON.stringify( kind.defaults ) ),
	key: `${ kind.prefix }${ randomHex() }`,
	source: 'db',
} );

const snapshot = ( item ) =>
	JSON.stringify( {
		name: item.name,
		title: item.title,
		active: item.active,
		settings: item.settings,
	} );

// Post types and taxonomies take their key from the singular name; options pages and connections from the title.
const nameSource = ( kind, item ) =>
	kind.kind === 'options_page' || kind.names?.name_from === 'title'
		? item.title
		: item.settings?.singular || '';

// A setting with `when` shows only while other settings have the given values.
export const isVisible = ( setting, settings ) =>
	! setting.when ||
	Object.entries( setting.when ).every(
		( [ name, value ] ) => ( settings?.[ name ] ?? '' ) === value
	);

export default function ContentEditor( { kind, itemKey, navigate } ) {
	const names = kind.names;
	const isNew = itemKey === 'new';
	const [ item, setItem ] = useState( () =>
		isNew ? blank( kind ) : null
	);
	const [ saved, setSaved ] = useState( () =>
		isNew ? snapshot( blank( kind ) ) : null
	);
	const [ stored, setStored ] = useState( ! isNew );
	const [ storedName, setStoredName ] = useState( '' );
	const [ loadError, setLoadError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ leaving, setLeaving ] = useState( false );
	const [ renaming, setRenaming ] = useState( null );
	const [ nameTouched, setNameTouched ] = useState( ! isNew );
	const titleRef = useRef();

	useEffect( () => {
		if ( isNew ) {
			titleRef.current?.focus();
			return;
		}
		getContent( kind.kind, itemKey )
			.then( ( loaded ) => {
				setItem( loaded );
				setSaved( snapshot( loaded ) );
				setStoredName( loaded.name );
			} )
			.catch( ( failure ) =>
				setLoadError(
					failure.message ||
						__(
							'This could not be loaded.',
							'custom-fields-meta-box'
						)
				)
			);
	}, [ kind.kind, itemKey, isNew ] );

	const readOnly = !! item && !! item.source && item.source !== 'db';
	const dirty = item !== null && ! readOnly && snapshot( item ) !== saved;

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

	const update = useCallback(
		( change ) =>
			setItem( ( current ) => {
				const next = { ...current, ...change };
				if ( ! nameTouched ) {
					next.name = suggestName(
						nameSource( kind, next ),
						kind.nameMaxLength
					);
				}
				return next;
			} ),
		[ kind, nameTouched ]
	);

	const setSetting = ( name, value ) =>
		update( { settings: { ...item.settings, [ name ]: value } } );

	const persist = async ( moveContent = false ) => {
		setSaving( true );
		setError( '' );
		const sent = item;

		try {
			const result = stored
				? await updateContent( kind.kind, sent, moveContent )
				: await createContent( kind.kind, sent );
			setItem( ( current ) =>
				current === sent ? result : { ...current, key: result.key }
			);
			setSaved( snapshot( result ) );
			setStoredName( result.name );
			setStored( true );
			setNameTouched( true );

			if ( result.moved ) {
				snackbar(
					sprintf(
						/* translators: %d: number of posts or terms */
						__(
							'Saved. %d items moved to the new key.',
							'custom-fields-meta-box'
						),
						result.moved
					)
				);
			} else if ( kind.kind === 'connection' ) {
				snackbar( __( 'Saved.', 'custom-fields-meta-box' ) );
			} else {
				snackbar(
					__(
						'Saved. Reload other admin pages to see the change in menus.',
						'custom-fields-meta-box'
					)
				);
			}
			syncNoteSnackbar( result.syncNote );

			if ( isNew ) {
				navigate( result.key, { replace: true, keepState: true } );
			}
		} catch ( failure ) {
			setError(
				failure.message ||
					__(
						'That could not be saved. Please try again.',
						'custom-fields-meta-box'
					)
			);
		} finally {
			setSaving( false );
		}
	};

	const save = () => {
		if ( saving || ! item || readOnly ) {
			return;
		}
		if ( ! item.title.trim() ) {
			setError( names.title_missing );
			titleRef.current?.focus();
			return;
		}
		if ( stored && storedName && storedName !== item.name ) {
			setRenaming( { move: kind.kind !== 'options_page' } );
			return;
		}
		persist();
	};

	const saveRef = useRef( save );
	saveRef.current = save;
	useEffect( () => {
		const onKeyDown = ( event ) => {
			if (
				( event.metaKey || event.ctrlKey ) &&
				event.key.toLowerCase() === 's'
			) {
				event.preventDefault();
				saveRef.current();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [] );

	const importToEdit = async () => {
		setSaving( true );
		setError( '' );
		try {
			const result = await syncPull( [
				{ kind: kind.kind, key: item.key },
			] );
			if ( result.errors?.length ) {
				throw new Error( result.errors[ 0 ].message );
			}
			const loaded = await getContent( kind.kind, item.key );
			setItem( loaded );
			setSaved( snapshot( loaded ) );
			setStoredName( loaded.name );
			snackbar(
				__(
					'Imported. You can edit it here now.',
					'custom-fields-meta-box'
				)
			);
		} catch ( failure ) {
			setError(
				failure.message ||
					__( 'It could not be imported.', 'custom-fields-meta-box' )
			);
		} finally {
			setSaving( false );
		}
	};

	const back = () => ( dirty ? setLeaving( true ) : navigate( '' ) );

	if ( loadError ) {
		return (
			<div className="cfmb-editor">
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
				<Button variant="link" onClick={ () => navigate( '' ) }>
					{ __( 'Back to the list', 'custom-fields-meta-box' ) }
				</Button>
			</div>
		);
	}

	if ( ! item ) {
		return (
			<div className="cfmb-editor cfmb-editor--loading">
				<Spinner /> { __( 'Loading…', 'custom-fields-meta-box' ) }
			</div>
		);
	}

	const singularSetting = kind.settings.find(
		( setting ) => setting.name === 'singular'
	);
	const shown = kind.settings.filter( ( setting ) =>
		isVisible( setting, item.settings )
	);
	const basic = shown.filter(
		( setting ) => ! setting.advanced && setting.name !== 'singular'
	);
	const advanced = shown.filter( ( setting ) => setting.advanced );
	const sections = [
		...new Set( advanced.map( ( setting ) => setting.section || 'other' ) ),
	];
	const singular = item.settings?.singular || '';
	const groupLink =
		stored &&
		( kind.kind === 'post_type' || kind.kind === 'taxonomy' ) &&
		config.pages?.groups
			? `${ config.pages.groups }&group=new&location=${
					kind.kind === 'post_type' ? 'post_type' : 'taxonomy'
			  }:${ encodeURIComponent( item.name ) }`
			: '';
	const optionsLink =
		stored && kind.kind === 'options_page' && config.pages?.groups
			? `${
					config.pages.groups
			  }&group=new&location=options_page:${ encodeURIComponent(
					item.name
			  ) }`
			: '';

	const control = ( setting ) => (
		<ContentSettingControl
			key={ setting.name }
			setting={ setting }
			value={ item.settings?.[ setting.name ] }
			singular={ singular }
			plural={ item.title }
			onChange={ ( value ) => setSetting( setting.name, value ) }
		/>
	);

	return (
		<div className="cfmb-editor cfmb-content-editor">
			<div className="cfmb-editor__bar">
				<Button
					variant="tertiary"
					icon={ uiIcon( 'arrow-left-alt2' ) }
					onClick={ back }
				>
					{ sprintf(
						/* translators: %s: plural kind name, e.g. Post Types */
						__( 'All %s', 'custom-fields-meta-box' ),
						names.plural
					) }
				</Button>
				<div className="cfmb-editor__status" aria-live="polite">
					{ dirty && (
						<span className="cfmb-editor__dirty">
							{ __(
								'Unsaved changes',
								'custom-fields-meta-box'
							) }
						</span>
					) }
				</div>
				{ readOnly ? (
					<Button
						variant="primary"
						isBusy={ saving }
						aria-disabled={ saving }
						onClick={ importToEdit }
					>
						{ __( 'Import to edit', 'custom-fields-meta-box' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						isBusy={ saving }
						aria-disabled={ saving }
						onClick={ save }
					>
						{ saving
							? __( 'Saving…', 'custom-fields-meta-box' )
							: __( 'Save', 'custom-fields-meta-box' ) }
					</Button>
				) }
			</div>

			{ readOnly && (
				<Notice
					status="info"
					isDismissible={ false }
					className="cfmb-editor__source"
				>
					{ item.source === 'code'
						? __(
								"This is registered in code, so changes here can't be saved. Import it to edit it here; then remove it from your code.",
								'custom-fields-meta-box'
						  )
						: __(
								'This comes from a sync file and is not in the database yet. Import it to edit it here.',
								'custom-fields-meta-box'
						  ) }
				</Notice>
			) }

			{ error && (
				<Notice
					status="error"
					onRemove={ () => setError( '' ) }
					className="cfmb-editor__error"
				>
					{ error }
				</Notice>
			) }

			{ ( groupLink || optionsLink ) && ! dirty && (
				<Notice
					status="success"
					isDismissible={ false }
					className="cfmb-editor__next"
				>
					{ __(
						'Add fields to it with a field group.',
						'custom-fields-meta-box'
					) }{ ' ' }
					<a href={ groupLink || optionsLink }>
						{ __(
							'Create a field group',
							'custom-fields-meta-box'
						) }
					</a>
				</Notice>
			) }

			<div className="cfmb-editor__layout">
				<div className="cfmb-editor__main">
					<Card className="cfmb-editor__card">
						<CardBody>
							<div className="cfmb-content-editor__names">
								<TextControl
									ref={ titleRef }
									{ ...common }
									__next40pxDefaultSize
									label={ names.title_label }
									placeholder={ names.title_example }
									value={ item.title }
									onChange={ ( title ) =>
										update( { title } )
									}
								/>
								{ singularSetting &&
									control( singularSetting ) }
								<TextControl
									{ ...common }
									__next40pxDefaultSize
									label={ names.name_label }
									help={ names.name_help }
									value={ item.name }
									maxLength={ kind.nameMaxLength }
									onChange={ ( name ) => {
										setNameTouched( true );
										setItem( ( current ) => ( {
											...current,
											name: name
												.toLowerCase()
												.replace(
													/[^a-z0-9_-]+/g,
													'_'
												),
										} ) );
									} }
								/>
							</div>
						</CardBody>
					</Card>

					<Card className="cfmb-editor__card">
						<CardHeader>
							<h2 className="cfmb-editor__card-title">
								{ __( 'Settings', 'custom-fields-meta-box' ) }
							</h2>
						</CardHeader>
						<CardBody className="cfmb-content-editor__settings">
							{ basic.map( control ) }
						</CardBody>
					</Card>

					<Card className="cfmb-editor__card cfmb-content-editor__advanced">
						<CardHeader>
							<h2 className="cfmb-editor__card-title">
								{ __( 'Advanced', 'custom-fields-meta-box' ) }
							</h2>
						</CardHeader>
						{ sections.map( ( section ) => (
							<PanelBody
								key={ section }
								title={ SECTIONS[ section ] || section }
								initialOpen={ false }
							>
								{ advanced
									.filter(
										( setting ) =>
											( setting.section || 'other' ) ===
											section
									)
									.map( control ) }
							</PanelBody>
						) ) }
					</Card>
				</div>

				<div className="cfmb-editor__side">
					<Card className="cfmb-editor__card">
						<CardBody>
							<ToggleControl
								{ ...common }
								label={ __(
									'Active',
									'custom-fields-meta-box'
								) }
								help={ __(
									'Inactive items are kept but not registered with WordPress. Their content stays in the database.',
									'custom-fields-meta-box'
								) }
								checked={ !! item.active }
								onChange={ ( active ) =>
									setItem( ( current ) => ( {
										...current,
										active,
									} ) )
								}
							/>
							<p className="cfmb-field-settings__key">
								{ __( 'Key:', 'custom-fields-meta-box' ) }{ ' ' }
								<code>{ item.key }</code>
							</p>
						</CardBody>
					</Card>
				</div>
			</div>

			{ leaving && (
				<ConfirmModal
					title={ __(
						'Leave without saving?',
						'custom-fields-meta-box'
					) }
					confirmLabel={ __( 'Leave', 'custom-fields-meta-box' ) }
					isDestructive
					onConfirm={ () => {
						setLeaving( false );
						navigate( '' );
					} }
					onCancel={ () => setLeaving( false ) }
				>
					<p>
						{ __(
							'Your changes have not been saved.',
							'custom-fields-meta-box'
						) }
					</p>
				</ConfirmModal>
			) }

			{ renaming && (
				<ConfirmModal
					title={ sprintf(
						/* translators: 1: old key, 2: new key */
						__(
							'Change the key from "%1$s" to "%2$s"?',
							'custom-fields-meta-box'
						),
						storedName,
						item.name
					) }
					confirmLabel={ __( 'Save', 'custom-fields-meta-box' ) }
					onConfirm={ () => {
						const move = renaming.move;
						setRenaming( null );
						persist( move );
					} }
					onCancel={ () => setRenaming( null ) }
				>
					{ kind.kind === 'options_page' && (
						<p>
							{ __(
								'Location rules that point at the old ID are updated. Values already saved stay under the old ID; copy them over if you need them.',
								'custom-fields-meta-box'
							) }
						</p>
					) }
					{ kind.kind === 'connection' && (
						<>
							<p>
								{ __(
									'Code that uses the old ID stops finding these links. Update your templates after saving.',
									'custom-fields-meta-box'
								) }
							</p>
							<CheckboxControl
								{ ...common }
								label={ names.move }
								checked={ renaming.move }
								onChange={ ( move ) => setRenaming( { move } ) }
							/>
						</>
					) }
					{ ( kind.kind === 'post_type' ||
						kind.kind === 'taxonomy' ) && (
						<>
							<p>
								{ __(
									'Content saved under the old key would no longer show in the admin. Location rules that point at the old key are updated either way.',
									'custom-fields-meta-box'
								) }
							</p>
							<CheckboxControl
								{ ...common }
								label={ names.move }
								checked={ renaming.move }
								onChange={ ( move ) => setRenaming( { move } ) }
							/>
						</>
					) }
				</ConfirmModal>
			) }
		</div>
	);
}
