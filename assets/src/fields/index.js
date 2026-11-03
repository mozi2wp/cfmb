/**
 * Mounts Field Groups on edit screens and blocks saving while visible
 * fields are invalid. Groups added to the page later (menu items added
 * over AJAX, widget forms re-rendered after a save, the Legacy Widget
 * block) are mounted as they appear.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import GroupForm from './GroupForm';
import { components } from './types';
import './fields.scss';

const NOTICE_ID = 'cfmb-invalid-fields';
const LIVE_STATUSES = [ 'publish', 'future', 'private' ];

window.cfmb = window.cfmb || {};
window.cfmb.fieldComponents = window.cfmb.fieldComponents || {};
// The Field Block editor renders the same forms inside blocks.
window.cfmb.GroupForm = GroupForm;
window.cfmb.fieldTypes = components;

const groups = new Map();
const listeners = new Set();
let onChange = () => {};

const notify = () => {
	onChange();
	listeners.forEach( ( listener ) => listener() );
};

const registry = {
	register( key, api ) {
		groups.set( key, api );
		notify();
	},
	unregister( key ) {
		groups.delete( key );
		notify();
	},
	changed() {
		notify();
	},
	invalid( enforceRequired ) {
		return Array.from( groups.values() ).flatMap( ( group ) =>
			group
				.invalid( enforceRequired )
				.map( ( item ) => ( { ...item, group: group.title } ) )
		);
	},
	reveal( enforceRequired ) {
		groups.forEach( ( group ) => group.reveal( enforceRequired ) );
	},
};

function focusFirstInvalid( invalid ) {
	// Let the reveal render first so the error, its tab and its row are visible.
	window.setTimeout( () => {
		const node = document.querySelector(
			`[data-cfmb-path="${ invalid[ 0 ].path }"]`
		);
		if ( ! node ) {
			return;
		}
		node.scrollIntoView( {
			block: 'center',
			behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' )
				.matches
				? 'auto'
				: 'smooth',
		} );
		node.querySelector( 'input, select, textarea, button' )?.focus( {
			preventScroll: true,
		} );
	}, 120 );
}

function announce( invalid ) {
	speak(
		sprintf(
			/* translators: %s: field label */
			__(
				'Some fields need attention, starting with "%s".',
				'custom-fields-meta-box'
			),
			invalid[ 0 ].field.label || invalid[ 0 ].group
		),
		'assertive'
	);
}

/**
 * Whether a classic form submission must have required fields filled in.
 * On the classic post screen, "Save Draft" skips the check, like the server does.
 *
 * @param {HTMLFormElement} form      Form.
 * @param {HTMLElement}     submitter Clicked button.
 * @return {boolean} Enforce.
 */
function enforceForForm( form, submitter ) {
	// The comment edit form is also #post, but has no post status.
	if (
		form.id !== 'post' ||
		! form.querySelector( '#original_post_status' )
	) {
		return true;
	}
	const original = form.querySelector( '#original_post_status' )?.value;
	return submitter?.id === 'publish' || LIVE_STATUSES.includes( original );
}

function resetClassicButtons() {
	document
		.querySelectorAll(
			'#submitpost .disabled, #submitpost .button.disabled'
		)
		.forEach( ( node ) => node.classList.remove( 'disabled' ) );
	document
		.querySelectorAll( '#submitpost .spinner.is-active' )
		.forEach( ( node ) => node.classList.remove( 'is-active' ) );
}

function guardForm( form ) {
	if ( ! form || form.dataset.cfmbGuarded ) {
		return;
	}
	form.dataset.cfmbGuarded = '1';

	const check = ( event, submitter ) => {
		const enforce = enforceForForm( form, submitter );
		const invalid = registry.invalid( enforce );
		if ( ! invalid.length ) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		registry.reveal( enforce );
		announce( invalid );
		focusFirstInvalid( invalid );
		window.setTimeout( resetClassicButtons, 0 );
	};

	form.addEventListener(
		'submit',
		( event ) => check( event, event.submitter ),
		true
	);

	// The add-term form saves over AJAX from a click handler and never submits.
	if ( form.id === 'addtag' ) {
		form.querySelector( '#submit' )?.addEventListener(
			'click',
			( event ) => check( event, event.currentTarget ),
			true
		);
	}
}

function watchBlockEditor() {
	const { data } = window.wp || {};
	if (
		! document.body.classList.contains( 'block-editor-page' ) ||
		! data?.select( 'core/editor' )
	) {
		return false;
	}

	let locked = false;

	const update = () => {
		const editor = data.select( 'core/editor' );
		const status = editor.getEditedPostAttribute( 'status' );
		const publishPanel =
			editor.isPublishSidebarOpened?.() ||
			data.select( 'core/edit-post' )?.isPublishSidebarOpened?.();
		const goingLive = LIVE_STATUSES.includes( status ) || publishPanel;
		const invalid = goingLive ? registry.invalid( true ) : [];

		if ( invalid.length && ! locked ) {
			locked = true;
			data.dispatch( 'core/editor' ).lockPostSaving( 'cfmb' );
			data.dispatch( 'core/notices' ).createWarningNotice(
				__(
					'Some fields need attention before this can be published.',
					'custom-fields-meta-box'
				),
				{
					id: NOTICE_ID,
					isDismissible: false,
					actions: [
						{
							label: __(
								'Show fields',
								'custom-fields-meta-box'
							),
							onClick: () => {
								const current = registry.invalid( true );
								registry.reveal( true );
								if ( current.length ) {
									announce( current );
									focusFirstInvalid( current );
								}
							},
						},
					],
				}
			);
		} else if ( ! invalid.length && locked ) {
			locked = false;
			data.dispatch( 'core/editor' ).unlockPostSaving( 'cfmb' );
			data.dispatch( 'core/notices' ).removeNotice( NOTICE_ID );
		}
	};

	onChange = () => {
		update();
		syncBindings( data );
	};
	data.subscribe( update );
	data.subscribe( () => syncBindings( data ) );
	return true;
}

let lastBindingEdits;

/**
 * Keep meta box fields and blocks bound to them (Block Bindings) in step.
 *
 * Edits made on the canvas live in the post's cfmb_bindings attribute and
 * are saved with the post; meta boxes are saved right after it. Copying
 * each canvas edit into the meta box form means the meta box save writes
 * the same value instead of the old one. Edits in the meta box go the other
 * way, so bound blocks show them right away.
 *
 * @param {Object} data wp.data.
 */
function syncBindings( data ) {
	const editor = data.select( 'core/editor' );
	const core = data.select( 'core' );
	const postId = editor?.getCurrentPostId?.();
	const postType = editor?.getCurrentPostType?.();

	if ( ! postId || ! postType || ! core?.getEntityRecord ) {
		return;
	}

	const record = core.getEntityRecord( 'postType', postType, postId );
	const bindable = record?.cfmb_bindings;
	if ( ! bindable || typeof bindable !== 'object' ) {
		return;
	}

	const objectRef = `post_${ postId }`;
	const edited =
		core.getEditedEntityRecord( 'postType', postType, postId )
			?.cfmb_bindings || bindable;
	const canvasEdits = core.getEntityRecordEdits?.(
		'postType',
		postType,
		postId
	)?.cfmb_bindings;

	if ( canvasEdits && canvasEdits !== lastBindingEdits ) {
		lastBindingEdits = canvasEdits;
		groups.forEach( ( group ) => {
			if ( group.objectRef === objectRef ) {
				Object.entries( canvasEdits ).forEach( ( [ name, value ] ) =>
					group.setByName?.( name, value )
				);
			}
		} );
		return;
	}

	const changes = {};
	groups.forEach( ( group ) => {
		if ( group.objectRef !== objectRef || ! group.valueByName ) {
			return;
		}
		Object.keys( bindable ).forEach( ( name ) => {
			const current = group.valueByName( name );
			if (
				current.found &&
				String( current.value ?? '' ) !== String( edited[ name ] ?? '' )
			) {
				changes[ name ] = current.value ?? '';
			}
		} );
	} );

	if ( Object.keys( changes ).length ) {
		lastBindingEdits = { ...edited, ...changes };
		data.dispatch( 'core' ).editEntityRecord(
			'postType',
			postType,
			postId,
			{ cfmb_bindings: lastBindingEdits }
		);
	}
}

function mount( container ) {
	if ( container.dataset.cfmbMounted ) {
		return;
	}

	const configNode = container.querySelector( '.cfmb-group__config' );
	const input = container.querySelector( '.cfmb-group__input' );
	const root = container.querySelector( '.cfmb-group__root' );

	if ( ! configNode || ! input || ! root ) {
		return;
	}

	let config;
	try {
		config = JSON.parse( configNode.textContent );
	} catch ( error ) {
		return;
	}

	container.dataset.cfmbMounted = '1';
	guardForm( container.closest( 'form' ) );

	createRoot( root ).render(
		<GroupForm
			config={ config }
			input={ input }
			registry={ registry }
			components={ { ...components, ...window.cfmb.fieldComponents } }
		/>
	);
}

/**
 * The block editor moves meta boxes into place after it starts. TinyMCE
 * iframes lose their content when moved, so mount only once a group sits
 * inside the editor's meta box area.
 *
 * @param {HTMLElement[]} containers Group containers.
 */
function mountWhenPlaced( containers ) {
	let tries = 0;
	const timer = window.setInterval( () => {
		tries++;
		containers.forEach( ( container ) => {
			// Meta boxes start in a hidden #metaboxes holder and are moved out of it.
			const placed =
				container.closest( '.edit-post-meta-boxes-area' ) ||
				! container.closest( '#metaboxes' );
			if ( placed || tries > 100 ) {
				mount( container );
			}
		} );
		if (
			containers.every( ( container ) => container.dataset.cfmbMounted )
		) {
			window.clearInterval( timer );
		}
	}, 100 );
}

/**
 * Mount groups that are added to the page after load.
 */
function watchForNewGroups() {
	if ( typeof window.MutationObserver !== 'function' ) {
		return;
	}

	let queued = false;
	const observer = new window.MutationObserver( () => {
		if ( queued ) {
			return;
		}
		queued = true;
		window.requestAnimationFrame( () => {
			queued = false;
			document
				.querySelectorAll(
					'.cfmb-group[data-cfmb-group]:not([data-cfmb-mounted])'
				)
				.forEach( mount );
		} );
	} );

	observer.observe( document.body, { childList: true, subtree: true } );
}

/**
 * Read-only view of the forms on the screen, for add-ons such as the SEO
 * analysis script: each entry has { key, title, objectRef, fields, values }.
 * subscribe() returns a function that stops listening.
 */
window.cfmb.forms = {
	list: () =>
		Array.from( groups.values() )
			.map( ( group ) => group.snapshot?.() )
			.filter( Boolean ),
	subscribe( listener ) {
		listeners.add( listener );
		return () => listeners.delete( listener );
	},
};

window.cfmb.mountGroups = ( root = document ) =>
	root
		.querySelectorAll(
			'.cfmb-group[data-cfmb-group]:not([data-cfmb-mounted])'
		)
		.forEach( mount );

domReady( () => {
	const containers = Array.from(
		document.querySelectorAll( '.cfmb-group[data-cfmb-group]' )
	);

	if ( watchBlockEditor() ) {
		if ( containers.length ) {
			mountWhenPlaced( containers );
		}
		return;
	}

	containers.forEach( mount );
	watchForNewGroups();
} );
