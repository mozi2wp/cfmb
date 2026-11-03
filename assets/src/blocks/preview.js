/**
 * Turns a Field Block's preview HTML into React elements, so the live inner
 * blocks area can sit where the template put <InnerBlocks />. The server
 * marks that spot with <cfmb-inner-blocks>.
 */
import { createElement } from '@wordpress/element';

const VOID = new Set( [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ] );

const RENAMED = {
	class: 'className',
	for: 'htmlFor',
	tabindex: 'tabIndex',
	readonly: 'readOnly',
	maxlength: 'maxLength',
	colspan: 'colSpan',
	rowspan: 'rowSpan',
	srcset: 'srcSet',
	crossorigin: 'crossOrigin',
	frameborder: 'frameBorder',
	allowfullscreen: 'allowFullScreen',
	autoplay: 'autoPlay',
	playsinline: 'playsInline',
	contenteditable: 'contentEditable',
	datetime: 'dateTime',
};

// Inputs in a preview are for looks only; React would warn about uncontrolled value props.
const VALUE_ATTRIBUTES = { value: 'defaultValue', checked: 'defaultChecked', selected: 'defaultSelected' };

/**
 * Inline style string to a React style object.
 *
 * @param {string} text CSS declarations.
 * @return {Object} Style.
 */
export function parseStyle( text ) {
	const style = {};
	String( text || '' )
		.split( ';' )
		.forEach( ( declaration ) => {
			const index = declaration.indexOf( ':' );
			if ( index < 1 ) {
				return;
			}
			const name = declaration.slice( 0, index ).trim();
			const value = declaration.slice( index + 1 ).trim();
			if ( ! name || ! value ) {
				return;
			}
			const key = name.startsWith( '--' ) ? name : name.toLowerCase().replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );
			style[ key ] = value;
		} );
	return style;
}

function parseJson( text, fallback ) {
	if ( text === undefined || text === null || text === '' ) {
		return fallback;
	}
	try {
		return JSON.parse( text );
	} catch ( error ) {
		return fallback;
	}
}

/**
 * Props for the inner blocks area from the marker's data attributes.
 *
 * @param {Element} node <cfmb-inner-blocks> element.
 * @return {Object} InnerBlocks props.
 */
export function innerBlocksProps( node ) {
	const props = {};
	const allowed = parseJson( node.getAttribute( 'data-allowedblocks' ), undefined );
	const template = parseJson( node.getAttribute( 'data-template' ), undefined );
	const lock = node.getAttribute( 'data-templatelock' );
	const orientation = node.getAttribute( 'data-orientation' );

	if ( Array.isArray( allowed ) ) {
		props.allowedBlocks = allowed;
	}
	if ( Array.isArray( template ) ) {
		props.template = template;
	}
	if ( lock !== null ) {
		props.templateLock = lock === 'false' || lock === '' ? false : lock;
	}
	if ( orientation === 'horizontal' || orientation === 'vertical' ) {
		props.orientation = orientation;
	}
	return props;
}

function elementProps( node, key ) {
	const props = { key };
	Array.from( node.attributes ).forEach( ( attribute ) => {
		const name = attribute.name;
		if ( /^on/i.test( name ) ) {
			return;
		}
		if ( name === 'style' ) {
			props.style = parseStyle( attribute.value );
			return;
		}
		props[ VALUE_ATTRIBUTES[ name ] || RENAMED[ name ] || name ] = attribute.value;
	} );
	return props;
}

function convert( node, key, slot ) {
	if ( node.nodeType === 3 ) {
		return node.textContent;
	}
	if ( node.nodeType !== 1 ) {
		return null;
	}

	const tag = node.nodeName.toLowerCase();

	if ( tag === 'cfmb-inner-blocks' ) {
		return slot ? slot( innerBlocksProps( node ), key ) : null;
	}
	if ( tag === 'script' ) {
		return null;
	}

	const props = elementProps( node, key );
	if ( VOID.has( tag ) ) {
		return createElement( tag, props );
	}

	const children = Array.from( node.childNodes ).map( ( child, index ) => convert( child, index, slot ) );
	return createElement( tag, props, ...children );
}

/**
 * Preview HTML as React elements.
 *
 * @param {string}   html Preview HTML from the server (escaped by the template).
 * @param {Function} slot Renders the inner blocks area: ( props, key ) => element.
 * @return {Array} Elements.
 */
export function htmlToElements( html, slot ) {
	const doc = new window.DOMParser().parseFromString( `<!DOCTYPE html><body>${ html || '' }</body>`, 'text/html' );
	return Array.from( doc.body.childNodes ).map( ( node, index ) => convert( node, index, slot ) );
}
