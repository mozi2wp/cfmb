/**
 * What every field inside a group form can reach: where values are saved,
 * error lookup, and the component for each field type.
 */
import { createContext, useContext } from '@wordpress/element';

export const FormContext = createContext( {
	groupKey: '',
	idBase: '',
	objectKind: '',
	objectId: 0,
	objectRef: '',
	components: {},
	reveal: null,
	errorFor: () => null,
	touch: () => {},
} );

export const useForm = () => useContext( FormContext );

/**
 * Join a parent path and a key: "fld_a" + "2" + "fld_b" -> "fld_a.2.fld_b".
 *
 * @param {...string} parts Parts.
 * @return {string} Path.
 */
export const joinPath = ( ...parts ) => parts.filter( ( part ) => part !== '' && part !== undefined && part !== null ).join( '.' );

/**
 * DOM-safe id from a path.
 *
 * @param {string} groupKey Group key.
 * @param {string} path     Field path.
 * @return {string} Id.
 */
export const domId = ( groupKey, path ) => `cfmb-${ groupKey }-${ String( path ).replace( /\./g, '-' ) }`;
