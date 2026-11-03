/**
 * Editor side of the cfmb/field Block Bindings source (WordPress 6.7+).
 *
 * Bound blocks show field values in the editor. Text-like fields of the post
 * being edited can be changed right on the canvas; those edits are kept in
 * the post's cfmb_bindings attribute and saved with the post. Everything
 * else (options pages, other posts, images, choices) is read-only here.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import { STORE } from './store';
import { pick, isCurrentObject, fieldList } from './source';

const config = window.cfmbBindings || {};

// Attributes that take a URL, ID or alt text; an empty value stays empty.
const PLAIN_ATTRIBUTES = [ 'url', 'href', 'alt', 'id', 'title' ];

const selectOf = ( args ) => args.select || args.registry?.select;

function currentRecord( select, context ) {
	if ( ! context?.postId || ! context?.postType ) {
		return null;
	}
	return select( 'core' ).getEditedEntityRecord( 'postType', context.postType, context.postId ) || null;
}

function labelFor( name, context ) {
	const list = fieldList( config, context?.postType );
	const found = list.find( ( item ) => item.args.field === name );
	/* translators: %s: field name */
	return found ? found.label : sprintf( __( 'Field: %s', 'custom-fields-meta-box' ), name );
}

if ( typeof registerBlockBindingsSource === 'function' ) {
	registerBlockBindingsSource( {
		name: 'cfmb/field',
		label: __( 'CFMB fields', 'custom-fields-meta-box' ),
		usesContext: [ 'postId', 'postType' ],

		getValues( args ) {
			const select = selectOf( args );
			const { context, bindings } = args;
			const values = {};

			Object.entries( bindings || {} ).forEach( ( [ attribute, binding ] ) => {
				const source = binding?.args || {};
				const name = source.field || source.key;
				if ( ! name ) {
					return;
				}

				if ( isCurrentObject( source.object ) ) {
					const editable = currentRecord( select, context )?.cfmb_bindings;
					if ( editable && Object.prototype.hasOwnProperty.call( editable, name ) ) {
						values[ attribute ] = editable[ name ];
						return;
					}
				}

				const post = isCurrentObject( source.object ) || source.object === 'author' ? context?.postId || 0 : 0;
				const parts = select( STORE ).getValue( source.object || '', post, name );
				const value = pick( parts, attribute );

				if ( value !== undefined && value !== '' ) {
					values[ attribute ] = value;
				} else if ( ! PLAIN_ATTRIBUTES.includes( attribute ) ) {
					// Empty text shows the field's name, so the bound block can still be found.
					values[ attribute ] = labelFor( name, context );
				}
			} );

			return values;
		},

		setValues( args ) {
			const select = selectOf( args );
			const dispatch = args.dispatch || args.registry?.dispatch;
			const { context, bindings } = args;
			const record = currentRecord( select, context );

			if ( ! record?.cfmb_bindings ) {
				return;
			}

			const next = { ...record.cfmb_bindings };
			let changed = false;

			Object.values( bindings || {} ).forEach( ( binding ) => {
				const source = binding?.args || {};
				const name = source.field || source.key;
				if ( name && isCurrentObject( source.object ) && Object.prototype.hasOwnProperty.call( next, name ) ) {
					next[ name ] = binding.newValue ?? '';
					changed = true;
				}
			} );

			if ( changed ) {
				dispatch( 'core' ).editEntityRecord( 'postType', context.postType, context.postId, { cfmb_bindings: next } );
			}
		},

		canUserEditValue( args ) {
			const select = selectOf( args );
			const { context } = args;
			const source = args.args || {};
			const name = source.field || source.key;

			if ( ! name || ! isCurrentObject( source.object ) ) {
				return false;
			}

			// Only the post being edited, not other posts shown in a Query Loop.
			const editorPost = select( 'core/editor' )?.getCurrentPostId?.();
			if ( ! context?.postId || String( editorPost ) !== String( context.postId ) ) {
				return false;
			}

			const editable = currentRecord( select, context )?.cfmb_bindings;
			return !! editable && Object.prototype.hasOwnProperty.call( editable, name );
		},

		getFieldsList( args ) {
			const select = selectOf( args );
			const postType = args.context?.postType || select?.( 'core/editor' )?.getCurrentPostType?.();
			return fieldList( config, postType );
		},
	} );
}
