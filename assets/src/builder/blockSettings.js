/**
 * Field Block settings of a group, without UI, so they can be unit tested.
 */

export const BLOCK_DEFAULTS = {
	enabled: false,
	name: '',
	title: '',
	description: '',
	icon: 'block-default',
	category: 'text',
	keywords: [],
	mode: 'preview',
	template: '',
	supports: {
		align: false,
		anchor: false,
		color_text: false,
		color_background: false,
		color_link: false,
		spacing_padding: false,
		spacing_margin: false,
		typography_font_size: false,
		typography_line_height: false,
		multiple: true,
		inserter: true,
	},
	alignments: [ 'wide', 'full' ],
	styles: [],
	variations: [],
};

/**
 * Block name part from text, the same way the server makes it.
 *
 * @param {string} text Text.
 * @return {string} Slug, empty when nothing usable is left.
 */
export function blockSlug( text ) {
	const slug = String( text || '' )
		.normalize( 'NFKD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 50 )
		.replace( /-+$/, '' );
	return /^[a-z]/.test( slug ) ? slug : '';
}

/**
 * Block settings with defaults filled in. Groups saved before Field Blocks
 * existed have none, and PHP sends an empty settings array as [].
 *
 * @param {*} raw Stored settings.
 * @return {Object} Settings.
 */
export function blockSettings( raw ) {
	const value = raw && typeof raw === 'object' && ! Array.isArray( raw ) ? raw : {};
	return {
		...BLOCK_DEFAULTS,
		...value,
		supports: { ...BLOCK_DEFAULTS.supports, ...( value.supports || {} ) },
		alignments: Array.isArray( value.alignments ) ? value.alignments : BLOCK_DEFAULTS.alignments,
		keywords: Array.isArray( value.keywords ) ? value.keywords : [],
		styles: Array.isArray( value.styles ) ? value.styles : [],
		variations: Array.isArray( value.variations ) ? value.variations : [],
	};
}
