/**
 * Registers the editor side of every Field Block. Titles, icons, supports
 * and attributes come from the server registration; this adds the editing UI.
 */
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import createEdit from './Edit';
import './blocks.scss';

const config = window.cfmbBlocks || { blocks: {} };

// Inner blocks are saved in the post; the block's own markup comes from its PHP template.
const save = () => <InnerBlocks.Content />;

Object.entries( config.blocks || {} ).forEach( ( [ name, definition ] ) => {
	if ( getBlockType( name ) ) {
		return;
	}
	registerBlockType( name, {
		edit: createEdit( name, definition ),
		save,
	} );
} );
