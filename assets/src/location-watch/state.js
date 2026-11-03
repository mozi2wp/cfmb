/**
 * What the location rules need from the post form, before it is saved.
 */

/**
 * Stable string for a state, to skip checks when nothing relevant changed.
 *
 * @param {Object} state State.
 * @return {string} Key.
 */
export function stateKey( state ) {
	const terms = Object.keys( state.terms || {} )
		.sort()
		.map( ( taxonomy ) => `${ taxonomy }:${ [ ...state.terms[ taxonomy ] ].map( String ).sort().join( ',' ) }` )
		.join( '|' );
	return [ state.template ?? '', state.format ?? '', state.status ?? '', state.product_type ?? '', terms ].join( '#' );
}

/**
 * State from the block editor.
 *
 * @param {Object} data     wp.data.
 * @param {string} postType Post type.
 * @return {Object|null} State, or null while the editor is loading.
 */
export function editorState( data, postType ) {
	const editor = data.select( 'core/editor' );
	if ( ! editor?.getCurrentPostId?.() ) {
		return null;
	}
	const attribute = ( name ) => editor.getEditedPostAttribute( name );
	const terms = {};
	const taxonomies = data.select( 'core' )?.getTaxonomies?.( { per_page: -1 } ) || [];

	taxonomies.forEach( ( taxonomy ) => {
		if ( ! ( taxonomy.types || [] ).includes( postType ) || ! taxonomy.rest_base ) {
			return;
		}
		const ids = attribute( taxonomy.rest_base );
		if ( Array.isArray( ids ) ) {
			terms[ taxonomy.slug ] = ids;
		}
	} );

	return {
		template: attribute( 'template' ) ?? '',
		format: attribute( 'format' ) || 'standard',
		status: attribute( 'status' ) === 'auto-draft' ? 'draft' : attribute( 'status' ) || 'draft',
		terms,
	};
}

/**
 * State from a classic post form.
 *
 * @param {HTMLFormElement} form Post form.
 * @return {Object} State.
 */
export function classicState( form ) {
	const state = { terms: {} };
	const template = form.querySelector( '#page_template' );
	if ( template ) {
		state.template = template.value === 'default' ? '' : template.value;
	}

	const format = form.querySelector( 'input[name="post_format"]:checked' );
	if ( format ) {
		state.format = format.value === '0' ? 'standard' : format.value;
	}

	const status = form.querySelector( '#post_status' );
	if ( status ) {
		state.status = status.value;
	}

	const productType = form.querySelector( '#product-type' );
	if ( productType ) {
		state.product_type = productType.value;
	}

	// Hierarchical taxonomies: ticked boxes. Categories post as post_category[].
	form.querySelectorAll( 'input[type="checkbox"][name^="tax_input["], input[type="checkbox"][name="post_category[]"]' ).forEach( ( input ) => {
		const match = input.name.match( /^tax_input\[([^\]]+)\]/ );
		const taxonomy = match ? match[ 1 ] : 'category';
		state.terms[ taxonomy ] = state.terms[ taxonomy ] || [];
		if ( input.checked && input.value !== '0' ) {
			state.terms[ taxonomy ].push( input.value );
		}
	} );

	// Flat taxonomies (tags): comma-separated names in a textarea.
	form.querySelectorAll( 'textarea[name^="tax_input["]' ).forEach( ( input ) => {
		const match = input.name.match( /^tax_input\[([^\]]+)\]/ );
		if ( match ) {
			state.terms[ match[ 1 ] ] = input.value
				.split( ',' )
				.map( ( name ) => name.trim() )
				.filter( Boolean );
		}
	} );

	return state;
}
