/**
 * Editor side of a Field Block: the preview on the canvas, the fields in
 * the sidebar, and a toolbar switch that shows the fields on the canvas.
 *
 * Values live in the block's `data` attribute, under the same flattened
 * names post meta uses. The forms work with editor values, so every edit is
 * sent to the server, which sanitizes and validates it like a meta box save
 * and returns the new `data`. Saving the post waits for that round trip.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { BlockControls, InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, Placeholder, Spinner, ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { useRegistry } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { renderBlock } from './api';
import { htmlToElements } from './preview';

const CONVERT_DELAY = 350;
const PREVIEW_DELAY = 250;

const isEmpty = ( value ) => ! value || typeof value !== 'object' || Object.keys( value ).length === 0;
const json = ( value ) => JSON.stringify( value ?? {} );

// Block forms report validity to the server round trip, not to the meta box save guard.
const localRegistry = { register() {}, unregister() {}, changed() {} };

/**
 * Attributes other than the field data: className, align, style and so on.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Presentation attributes.
 */
export function presentationOf( attributes ) {
	const { data, mode, ...rest } = attributes || {};
	return rest;
}

/**
 * Flat list of error messages from the server, for a notice.
 *
 * @param {Object} errors Errors keyed by field path.
 * @param {Array}  groups Block groups.
 * @return {string[]} Messages.
 */
export function errorMessages( errors, groups ) {
	const labels = {};
	const collect = ( fields ) =>
		( fields || [] ).forEach( ( field ) => {
			labels[ field.key ] = field.label || field.name;
			collect( field.fields );
			( field.layouts || [] ).forEach( ( layout ) => collect( layout.fields ) );
		} );
	groups.forEach( ( group ) => collect( group.fields ) );

	return Object.entries( errors || {} ).map( ( [ path, message ] ) => {
		const key = String( path ).split( '.' ).pop();
		return labels[ key ] ? `${ labels[ key ] }: ${ message }` : message;
	} );
}

function FormHost( { name, groups, clientId, latest, hostRef, onEdit, inline } ) {
	const GroupForm = window.cfmb?.GroupForm;
	const [ start ] = useState( () => ( { base: latest.current.data, values: latest.current.values } ) );
	const [ id ] = useState( () => Math.random().toString( 36 ).slice( 2 ) );

	useEffect( () => {
		hostRef.current = { id, base: start.base, submissions: {} };
		return () => {
			if ( hostRef.current?.id === id ) {
				hostRef.current = null;
			}
		};
	}, [ id, start, hostRef ] );

	const inputs = useMemo( () => {
		const out = {};
		groups.forEach( ( group ) => {
			let first = true;
			out[ group.key ] = {
				// GroupForm writes its serialized values here after every change.
				set value( serialized ) {
					// The first write only mirrors the starting values.
					if ( first ) {
						first = false;
						return;
					}
					onEdit( id, group.key, serialized );
				},
				get value() {
					return '';
				},
			};
		} );
		return out;
	}, [ groups, id, onEdit ] );

	if ( ! GroupForm ) {
		return <p>{ __( 'The field editor could not load. Reload the page.', 'custom-fields-meta-box' ) }</p>;
	}

	if ( ! groups.length ) {
		return <p className="cfmb-block-form__empty">{ __( 'This block has no fields yet. Add fields to its field group under CFMB.', 'custom-fields-meta-box' ) }</p>;
	}

	const components = { ...( window.cfmb.fieldTypes || {} ), ...( window.cfmb.fieldComponents || {} ) };

	return groups.map( ( group ) => {
		const form = (
			<GroupForm
				config={ {
					key: group.key,
					title: group.title,
					labelPlacement: group.labelPlacement,
					fields: group.fields,
					values: start.values?.[ group.key ] || group.defaults || {},
					errors: {},
					objectKind: 'block',
					objectId: 0,
					objectPage: '',
					objectRef: `block:${ name }`,
					instance: `${ group.key }@${ clientId }`,
					idBase: `${ group.key }-${ clientId.slice( 0, 8 ) }${ inline ? '-c' : '' }`,
				} }
				input={ inputs[ group.key ] }
				registry={ localRegistry }
				components={ components }
			/>
		);

		if ( inline ) {
			return (
				<div className="cfmb-block-form__group" key={ group.key }>
					{ groups.length > 1 && <h3 className="cfmb-block-form__title">{ group.title }</h3> }
					{ group.description && <p className="cfmb-group__description">{ group.description }</p> }
					{ form }
				</div>
			);
		}

		return (
			<PanelBody key={ group.key } title={ group.title } initialOpen>
				{ group.description && <p className="cfmb-group__description">{ group.description }</p> }
				<div className={ `cfmb-group cfmb-group--plain cfmb-group--labels-${ group.labelPlacement || 'top' }` }>{ form }</div>
			</PanelBody>
		);
	} );
}

export default function createEdit( name, definition ) {
	const groups = definition.groups || [];
	const defaults = Object.fromEntries( groups.map( ( group ) => [ group.key, group.defaults || {} ] ) );

	return function Edit( { attributes, setAttributes, clientId, context, isSelected } ) {
		const registry = useRegistry();
		const mode = attributes.mode || definition.mode || 'preview';
		const data = attributes.data || {};
		const dataJson = json( data );
		const presentation = json( presentationOf( attributes ) );
		const postId = context?.postId;
		const postType = context?.postType;
		const requestContext = useMemo( () => ( postId ? { postId, postType } : {} ), [ postId, postType ] );

		const [ ready, setReady ] = useState( () => isEmpty( data ) );
		const [ hostVersion, setHostVersion ] = useState( 0 );
		const [ html, setHtml ] = useState( null );
		const [ errors, setErrors ] = useState( {} );
		const [ failure, setFailure ] = useState( '' );

		const latest = useRef( { data, values: defaults } );
		const known = useRef( dataJson );
		const hostRef = useRef( null );
		const timer = useRef( null );
		const loadSeq = useRef( 0 );
		const convertSeq = useRef( 0 );
		const busy = useRef( 0 );
		const attributesRef = useRef( attributes );
		attributesRef.current = attributes;
		const modeRef = useRef( mode );
		modeRef.current = mode;
		// What the shown preview was rendered from, so the same preview isn't fetched twice.
		const previewOf = useRef( '' );
		const previewKey = ( current ) => `${ json( presentationOf( current ) ) }|${ json( current.data ) }`;

		const lockKey = `cfmb-block-${ clientId }`;
		const setBusy = useCallback(
			( change ) => {
				const before = busy.current;
				busy.current = Math.max( 0, busy.current + change );
				const editor = registry.dispatch( 'core/editor' );
				if ( ! editor?.lockPostSaving ) {
					return;
				}
				if ( before === 0 && busy.current > 0 ) {
					editor.lockPostSaving( lockKey );
				} else if ( before > 0 && busy.current === 0 ) {
					editor.unlockPostSaving( lockKey );
				}
			},
			[ registry, lockKey ]
		);

		useEffect( () => () => {
			window.clearTimeout( timer.current );
			if ( busy.current ) {
				registry.dispatch( 'core/editor' )?.unlockPostSaving?.( lockKey );
			}
		}, [ registry, lockKey ] );

		// Saved data the forms have not seen yet: on load, and after undo or redo.
		const loadSnapshot = useCallback( () => {
			const seq = ++loadSeq.current;
			const current = attributesRef.current;
			setBusy( 1 );
			renderBlock( {
				name,
				data: current.data || {},
				attributes: presentationOf( current ),
				context: requestContext,
				preview: modeRef.current === 'preview',
				form: true,
			} )
				.then( ( result ) => {
					if ( seq !== loadSeq.current ) {
						return;
					}
					latest.current = { data: result.data || {}, values: result.values || defaults };
					known.current = json( current.data );
					setFailure( '' );
					if ( modeRef.current === 'preview' ) {
						previewOf.current = previewKey( current );
						setHtml( result.html || '' );
					}
					setReady( true );
					setHostVersion( ( version ) => version + 1 );
				} )
				.catch( ( error ) => setFailure( error?.message || __( 'The block could not be loaded.', 'custom-fields-meta-box' ) ) )
				.finally( () => setBusy( -1 ) );
		}, [ requestContext, setBusy ] );

		useEffect( () => {
			if ( ! ready || dataJson !== known.current ) {
				known.current = dataJson;
				if ( isEmpty( data ) ) {
					latest.current = { data: {}, values: defaults };
					setReady( true );
					setHostVersion( ( version ) => version + 1 );
					return;
				}
				loadSnapshot();
			}
			// Only data changes made outside the forms matter here.
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ dataJson ] );

		// Preview for the current data and presentation (className, align, colors).
		useEffect( () => {
			if ( mode !== 'preview' || ! ready || previewOf.current === previewKey( attributesRef.current ) ) {
				return undefined;
			}
			const seq = ++loadSeq.current;
			const handle = window.setTimeout( () => {
				renderBlock( {
					name,
					data: attributesRef.current.data || {},
					attributes: presentationOf( attributesRef.current ),
					context: requestContext,
					preview: true,
					form: false,
				} )
					.then( ( result ) => {
						if ( seq === loadSeq.current ) {
							previewOf.current = previewKey( attributesRef.current );
							setFailure( '' );
							setHtml( result.html || '' );
						}
					} )
					.catch( ( error ) => setFailure( error?.message || __( 'The preview could not be loaded.', 'custom-fields-meta-box' ) ) );
			}, html === null ? 0 : PREVIEW_DELAY );
			return () => window.clearTimeout( handle );
			// The preview follows presentation and mode; data changes bring their own preview.
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ presentation, mode, ready, requestContext ] );

		const convert = useCallback( () => {
			timer.current = null;
			const host = hostRef.current;
			if ( ! host ) {
				setBusy( -1 );
				return;
			}
			const seq = ++convertSeq.current;
			const hostId = host.id;
			renderBlock( {
				name,
				data: host.base,
				values: host.submissions,
				attributes: presentationOf( attributesRef.current ),
				context: requestContext,
				preview: modeRef.current === 'preview',
				form: true,
			} )
				.then( ( result ) => {
					if ( seq !== convertSeq.current ) {
						return;
					}
					const next = result.data || {};
					latest.current = { data: next, values: result.values || latest.current.values };
					known.current = json( next );
					setAttributes( { data: next } );
					setErrors( result.errors || {} );
					setFailure( '' );
					if ( modeRef.current === 'preview' && typeof result.html === 'string' ) {
						loadSeq.current++;
						previewOf.current = previewKey( { ...attributesRef.current, data: next } );
						setHtml( result.html );
					}
					// A form that opened while this was on its way started from older values.
					if ( hostRef.current && hostRef.current.id !== hostId ) {
						setHostVersion( ( version ) => version + 1 );
					}
				} )
				.catch( ( error ) => setFailure( error?.message || __( 'The changes could not be applied. Try again.', 'custom-fields-meta-box' ) ) )
				.finally( () => setBusy( -1 ) );
		}, [ requestContext, setAttributes, setBusy ] );

		const onEdit = useCallback(
			( hostId, groupKey, serialized ) => {
				const host = hostRef.current;
				if ( ! host || host.id !== hostId ) {
					return;
				}
				try {
					host.submissions[ groupKey ] = JSON.parse( serialized );
				} catch ( error ) {
					return;
				}
				if ( timer.current ) {
					window.clearTimeout( timer.current );
				} else {
					setBusy( 1 );
				}
				timer.current = window.setTimeout( convert, CONVERT_DELAY );
			},
			[ convert, setBusy ]
		);

		const switchMode = () => setAttributes( { mode: mode === 'edit' ? 'preview' : 'edit' } );

		const blockProps = useBlockProps( {
			className: `cfmb-block-editor cfmb-block-editor--${ mode }`,
			// Links in a preview would leave the editor.
			onClickCapture: ( event ) => {
				if ( mode === 'preview' && event.target.closest?.( 'a' ) ) {
					event.preventDefault();
				}
			},
		} );

		const messages = errorMessages( errors, groups );
		const notices = (
			<>
				{ failure && (
					<Notice status="error" isDismissible={ false }>
						{ failure }
					</Notice>
				) }
				{ messages.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						<p>{ __( 'These values were not saved:', 'custom-fields-meta-box' ) }</p>
						<ul>
							{ messages.map( ( message ) => (
								<li key={ message }>{ message }</li>
							) ) }
						</ul>
					</Notice>
				) }
			</>
		);

		const host = ready && (
			<FormHost
				key={ `${ mode }-${ hostVersion }` }
				name={ name }
				groups={ groups }
				clientId={ clientId }
				latest={ latest }
				hostRef={ hostRef }
				onEdit={ onEdit }
				inline={ mode === 'edit' }
			/>
		);

		const innerBlocksSlot = ( props, key ) => <InnerBlocks key={ key } { ...props } />;

		let canvas;
		if ( definition.templateError && mode === 'preview' ) {
			canvas = (
				<Placeholder icon="warning" label={ __( 'Template missing', 'custom-fields-meta-box' ) } instructions={ definition.templateError } />
			);
		} else if ( mode === 'edit' ) {
			canvas = (
				<div className="cfmb-block-form">
					{ notices }
					{ host || <Spinner /> }
					{ definition.innerBlocks && (
						<div className="cfmb-block-form__inner">
							<InnerBlocks />
						</div>
					) }
				</div>
			);
		} else if ( html === null ) {
			canvas = (
				<div className="cfmb-block-editor__loading">
					<Spinner />
				</div>
			);
		} else if ( html.trim() === '' && ! definition.innerBlocks ) {
			canvas = (
				<Placeholder
					icon="edit"
					label={ __( 'Nothing to show yet', 'custom-fields-meta-box' ) }
					instructions={ __( 'Fill in the block\'s fields in the sidebar, or switch to editing the fields here.', 'custom-fields-meta-box' ) }
				/>
			);
		} else {
			canvas = <div className="cfmb-block-editor__preview">{ htmlToElements( html, definition.innerBlocks ? innerBlocksSlot : null ) }</div>;
		}

		return (
			<>
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							icon={ mode === 'edit' ? 'visibility' : 'edit' }
							label={ mode === 'edit' ? __( 'Show preview', 'custom-fields-meta-box' ) : __( 'Edit fields in the block', 'custom-fields-meta-box' ) }
							onClick={ switchMode }
							isPressed={ mode === 'edit' }
						/>
					</ToolbarGroup>
				</BlockControls>
				{ isSelected && (
					<InspectorControls>
						{ mode === 'preview' ? (
							<>
								<div className="cfmb-block-sidebar__notices">{ notices }</div>
								{ host || (
									<PanelBody>
										<Spinner />
									</PanelBody>
								) }
							</>
						) : (
							<PanelBody>
								<p>{ __( 'The fields are shown in the block. Use the preview button in the toolbar to see the result.', 'custom-fields-meta-box' ) }</p>
							</PanelBody>
						) }
					</InspectorControls>
				) }
				<div { ...blockProps }>{ canvas }</div>
			</>
		);
	};
}
