/**
 * Classic WordPress editor (TinyMCE + Text tab) through wp.oldEditor.
 */
import { useEffect, useRef } from '@wordpress/element';

const BASIC_TOOLBAR =
	'bold,italic,underline,blockquote,strikethrough,bullist,numlist,alignleft,aligncenter,alignright,undo,redo,link';
const FULL_TOOLBAR_1 =
	'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,wp_more,spellchecker,fullscreen,wp_adv';
const FULL_TOOLBAR_2 =
	'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help';

export default function Wysiwyg( { field, id, value, onChange, describedBy } ) {
	const settings = field.settings || {};
	const textareaRef = useRef();
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	useEffect( () => {
		const oldEditor = window.wp?.oldEditor;
		const textarea = textareaRef.current;

		if ( ! oldEditor || ! textarea ) {
			return undefined;
		}

		const tabs = settings.tabs || 'all';
		const emit = () => onChangeRef.current( textarea.value );

		const tinymce =
			tabs === 'text'
				? false
				: {
						wpautop: true,
						toolbar1:
							settings.toolbar === 'basic'
								? BASIC_TOOLBAR
								: FULL_TOOLBAR_1,
						toolbar2:
							settings.toolbar === 'basic' ? '' : FULL_TOOLBAR_2,
						setup( editor ) {
							editor.on(
								'change keyup input NodeChange SetContent',
								() => {
									editor.save();
									emit();
								}
							);
						},
				  };

		oldEditor.initialize( id, {
			tinymce,
			quicktags: tabs !== 'visual',
			mediaButtons: settings.media_upload !== false,
		} );

		// Quicktags buttons edit the textarea without input events.
		textarea.addEventListener( 'input', emit );
		textarea.addEventListener( 'change', emit );
		const toolbar = document.getElementById( `qt_${ id }_toolbar` );
		toolbar?.addEventListener( 'click', emit );

		return () => {
			textarea.removeEventListener( 'input', emit );
			textarea.removeEventListener( 'change', emit );
			toolbar?.removeEventListener( 'click', emit );
			oldEditor.remove( id );
		};
		// The editor manages its own content after the first render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ id ] );

	return (
		<div className="cfmb-wysiwyg">
			<textarea
				ref={ textareaRef }
				id={ id }
				className="wp-editor-area"
				rows={ Number( settings.rows ) || 10 }
				defaultValue={ value ?? '' }
				aria-describedby={ describedBy }
			/>
		</div>
	);
}
