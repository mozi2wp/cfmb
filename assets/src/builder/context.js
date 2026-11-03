/**
 * Shared state for every field list in the group being edited, so a field
 * can be dragged or moved from one list to another.
 */
import { createContext, useContext } from '@wordpress/element';

export const BuilderContext = createContext( {
	groupKey: '',
	dragRef: { current: null },
	moveField: () => {},
	lists: () => [],
} );

export const useBuilder = () => useContext( BuilderContext );
