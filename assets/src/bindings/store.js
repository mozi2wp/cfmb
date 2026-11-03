/**
 * Values bound blocks show in the editor, fetched once per object and field.
 */
import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';

export const STORE = 'cfmb/bindings';

export const keyOf = ( object, post, field ) =>
	`${ object || '' }|${ post || 0 }|${ field }`;

const store = createReduxStore( STORE, {
	reducer( state = {}, action ) {
		if ( action.type === 'RECEIVE' ) {
			return { ...state, [ action.key ]: action.value };
		}
		return state;
	},
	actions: {
		receive: ( key, value ) => ( { type: 'RECEIVE', key, value } ),
	},
	selectors: {
		getValue: ( state, object, post, field ) =>
			state[ keyOf( object, post, field ) ],
	},
	resolvers: {
		getValue:
			( object, post, field ) =>
			async ( { dispatch } ) => {
				const key = keyOf( object, post, field );
				try {
					const result = await apiFetch( {
						path: addQueryArgs( '/cfmb/v1/bindings', {
							object: object || '',
							post: post || 0,
							fields: [ field ],
						} ),
					} );
					dispatch.receive( key, result?.[ field ] ?? null );
				} catch ( error ) {
					dispatch.receive( key, null );
				}
			},
	},
} );

register( store );
