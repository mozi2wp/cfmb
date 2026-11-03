/**
 * Shows or hides field group boxes as soon as the template, post format,
 * status, terms or product type change in the post form, instead of only
 * after saving and reloading.
 *
 * Works in the block editor (reads core/editor) and on classic screens
 * (reads the form). The server decides which groups match.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { speak } from '@wordpress/a11y';
import { __ } from '@wordpress/i18n';
import { classicState, editorState, stateKey } from './state';

const config = {
	postId: 0,
	postType: '',
	groups: [],
	...( window.cfmbLocationWatch || {} ),
};
const WAITING = 'cfmb-box-waiting';
const DELAY = 300;

let lastKey = '';
let timer = 0;
let request = 0;

function box( key ) {
	return document.getElementById( `cfmb-${ key }` );
}

function apply( matched ) {
	let shown = 0;
	config.groups.forEach( ( key ) => {
		const node = box( key );
		if ( ! node ) {
			return;
		}
		const show = matched.includes( key );
		if ( show && node.classList.contains( WAITING ) ) {
			shown++;
		}
		node.classList.toggle( WAITING, ! show );
	} );
	// Maps and editors inside a box that just appeared need to measure again.
	window.dispatchEvent( new window.Event( 'resize' ) );
	if ( shown ) {
		speak(
			__(
				'More fields are available for this post.',
				'custom-fields-meta-box'
			)
		);
	}
}

function check( state ) {
	const key = stateKey( state );
	if ( key === lastKey ) {
		return;
	}
	lastKey = key;
	window.clearTimeout( timer );
	timer = window.setTimeout( () => {
		const id = ++request;
		apiFetch( {
			path: '/cfmb/v1/location/match',
			method: 'POST',
			data: {
				post_id: config.postId,
				post_type: config.postType,
				groups: config.groups,
				...state,
			},
		} )
			.then( ( response ) => {
				// An older answer must not undo a newer one.
				if ( id === request && Array.isArray( response?.groups ) ) {
					apply( response.groups );
				}
			} )
			.catch( () => {} );
	}, DELAY );
}

function watchBlockEditor( data ) {
	let ready = false;
	data.subscribe( () => {
		const state = editorState( data, config.postType );
		if ( ! state ) {
			return;
		}
		// The first state is what the page was built for; only changes need a check.
		if ( ! ready ) {
			ready = true;
			lastKey = stateKey( state );
			return;
		}
		check( state );
	} );
}

function watchClassic() {
	const form = document.getElementById( 'post' );
	if ( ! form ) {
		return;
	}
	lastKey = stateKey( classicState( form ) );
	const onChange = () => check( classicState( form ) );
	form.addEventListener( 'change', onChange );
	// Tags are added with buttons and Enter, which change the hidden textarea without a change event.
	form.addEventListener( 'click', () => window.setTimeout( onChange, 50 ) );
	form.addEventListener( 'keyup', ( event ) => {
		if ( event.key === 'Enter' || event.key === ',' ) {
			window.setTimeout( onChange, 50 );
		}
	} );
}

domReady( () => {
	if ( ! config.groups.length ) {
		return;
	}
	const data = window.wp?.data;
	if (
		document.body.classList.contains( 'block-editor-page' ) &&
		data?.select( 'core/editor' )
	) {
		watchBlockEditor( data );
		return;
	}
	watchClassic();
} );
