/**
 * Connection boxes on post, term and user screens.
 *
 * Each `.cfmb-connection` element carries its config in
 * data-cfmb-connection (see Connection_Panels::render_box()). The box
 * replaces the server's hidden inputs with its own, in the chosen order.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import ConnectionBox from './ConnectionBox';
import './connections.scss';

function mount( element ) {
	if ( element.dataset.cfmbMounted ) {
		return;
	}

	let config;
	try {
		config = JSON.parse( element.dataset.cfmbConnection || '{}' );
	} catch {
		return;
	}

	element.dataset.cfmbMounted = '1';
	createRoot( element ).render( <ConnectionBox config={ config } /> );
}

domReady( () => {
	document.querySelectorAll( '.cfmb-connection[data-cfmb-connection]' ).forEach( mount );
} );
