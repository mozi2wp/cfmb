/**
 * Adds CFMB field text to Yoast SEO and Rank Math content analysis.
 *
 * The text is read from the forms on the screen (window.cfmb.forms) each
 * time the SEO plugin asks, and the analysis is refreshed shortly after a
 * field changes. Nothing is saved.
 */
import { addFilter } from '@wordpress/hooks';
import domReady from '@wordpress/dom-ready';
import { contentFor } from './content';

const config = { plugins: [], fields: [], ...( window.cfmbSeo || {} ) };
const wanted = new Set( config.fields );
const PLUGIN = 'cfmb';
const DELAY = 600;

// Attachment details arrive over AJAX; keep what was fetched so each refresh is instant.
const attachments = new Map();

function attachment( id ) {
	if ( attachments.has( id ) ) {
		return attachments.get( id );
	}
	const media = window.wp?.media;
	if ( ! media?.attachment ) {
		return null;
	}
	attachments.set( id, null );
	const model = media.attachment( id );
	model
		.fetch()
		.then( () => {
			attachments.set( id, {
				url: model.get( 'url' ) || '',
				alt: model.get( 'alt' ) || '',
			} );
			scheduleRefresh();
		} )
		.catch( () => {} );
	return null;
}

function collect() {
	const forms = window.cfmb?.forms?.list?.() || [];
	return forms
		.map( ( form ) =>
			contentFor( form.fields, form.values, wanted, attachment )
		)
		.join( '' );
}

let timer = 0;
const refreshers = [];

function scheduleRefresh() {
	window.clearTimeout( timer );
	timer = window.setTimeout(
		() => refreshers.forEach( ( refresh ) => refresh() ),
		DELAY
	);
}

function setupRankMath() {
	addFilter(
		'rank_math_content',
		PLUGIN,
		( content ) => `${ content || '' }${ collect() }`
	);
	refreshers.push( () => window.rankMathEditor?.refresh?.( 'content' ) );
}

function setupYoast() {
	let ready = false;
	const register = () => {
		const app = window.YoastSEO?.app;
		if ( ready || ! app?.registerPlugin ) {
			return;
		}
		ready = true;
		app.registerPlugin( PLUGIN, { status: 'ready' } );
		app.registerModification(
			'content',
			( content ) => `${ content || '' }${ collect() }`,
			PLUGIN,
			5
		);
	};

	register();
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'YoastSEO:ready', register );
	}
	window.addEventListener( 'YoastSEO:ready', register );
	refreshers.push( () => {
		register();
		window.YoastSEO?.app?.pluginReloaded?.( PLUGIN );
	} );
}

if ( config.plugins.includes( 'rankmath' ) ) {
	setupRankMath();
}

domReady( () => {
	if ( config.plugins.includes( 'yoast' ) ) {
		setupYoast();
	}
	window.cfmb?.forms?.subscribe?.( scheduleRefresh );
	scheduleRefresh();
} );
