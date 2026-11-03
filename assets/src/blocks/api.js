/**
 * The REST route behind Field Blocks.
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Clean data, apply form edits, get form values and the preview.
 *
 * @param {Object}  args            Request.
 * @param {string}  args.name       Block name.
 * @param {Object}  args.data       Block data to start from.
 * @param {Object}  args.values     Form values by group key (optional).
 * @param {Object}  args.attributes Other block attributes, for the preview.
 * @param {Object}  args.context    Block context (postId, postType).
 * @param {boolean} args.preview    Whether to render the preview.
 * @param {boolean} args.form       Whether to return form values.
 * @return {Promise<{data: Object, errors: Object, values?: Object, html?: string}>} Result.
 */
export const renderBlock = ( args ) =>
	apiFetch( {
		path: '/cfmb/v1/blocks/render',
		method: 'POST',
		data: args,
	} );
