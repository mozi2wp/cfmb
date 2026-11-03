/**
 * Field Group builder entry point.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './components/App';
import './builder.scss';

domReady( () => {
	const container = document.getElementById( 'cfmb-builder' );
	if ( container ) {
		createRoot( container ).render( <App /> );
	}
} );
