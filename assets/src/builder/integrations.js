/**
 * Per-field options for translation, SEO and GraphQL plugins. Mirrors
 * CFMB\Integrations\Field_Integrations: only values that differ from the
 * defaults are kept on the field.
 */

const TRANSLATED_TYPES = [ 'text', 'textarea', 'wysiwyg', 'url', 'oembed', 'link' ];
const SEO_TYPES = [ 'text', 'textarea', 'wysiwyg', 'image', 'gallery' ];

export const TRANSLATION_MODES = [ 'translate', 'copy', 'copy_once', 'ignore' ];

export const defaultTranslation = ( type ) => ( TRANSLATED_TYPES.includes( type ) ? 'translate' : 'copy' );

export const defaultSeo = ( type ) => SEO_TYPES.includes( type );

/**
 * Current options with defaults filled in.
 *
 * @param {Object} field Field definition.
 * @return {{translation: string, seo: boolean, graphql: boolean}} Options.
 */
export function readIntegrations( field ) {
	const options = field.integrations || {};
	return {
		translation: TRANSLATION_MODES.includes( options.translation ) ? options.translation : '',
		seo: typeof options.seo === 'boolean' ? options.seo : defaultSeo( field.type ),
		graphql: options.graphql !== false,
	};
}

/**
 * Options to store after a change: defaults are dropped.
 *
 * @param {Object} field  Field definition.
 * @param {Object} change Changed options.
 * @return {Object|undefined} Options, or undefined when everything is at its default.
 */
export function nextIntegrations( field, change ) {
	const merged = { ...readIntegrations( field ), ...change };
	const out = {};

	if ( TRANSLATION_MODES.includes( merged.translation ) ) {
		out.translation = merged.translation;
	}
	if ( merged.seo !== defaultSeo( field.type ) ) {
		out.seo = merged.seo;
	}
	if ( merged.graphql === false ) {
		out.graphql = false;
	}

	return Object.keys( out ).length ? out : undefined;
}
