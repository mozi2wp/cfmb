/**
 * Single choice shown as a row of buttons, with radio semantics.
 */
import { useRef } from '@wordpress/element';

export default function ButtonGroup( { field, id, value, onChange } ) {
	const settings = field.settings || {};
	const choices = settings.choices || [];
	const refs = useRef( [] );
	const activeIndex = Math.max(
		0,
		choices.findIndex( ( choice ) => choice.value === value )
	);

	const pick = ( choice ) => {
		const deselect = settings.allow_null && choice.value === value;
		onChange( deselect ? '' : choice.value );
	};

	const onKeyDown = ( event, index ) => {
		let step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[
			event.key
		];
		if ( ! step || ! choices.length ) {
			return;
		}
		event.preventDefault();
		// Left and right follow visual order, which flips in RTL.
		if (
			document.dir === 'rtl' &&
			( event.key === 'ArrowLeft' || event.key === 'ArrowRight' )
		) {
			step = -step;
		}
		const next = ( index + step + choices.length ) % choices.length;
		onChange( choices[ next ].value );
		refs.current[ next ]?.focus();
	};

	return (
		<div
			className="cfmb-button-group"
			role="radiogroup"
			id={ id }
			aria-label={ field.label }
		>
			{ choices.map( ( choice, index ) => {
				const checked = choice.value === value;
				return (
					<button
						key={ choice.value }
						ref={ ( node ) => ( refs.current[ index ] = node ) }
						type="button"
						role="radio"
						aria-checked={ checked }
						tabIndex={ index === activeIndex ? 0 : -1 }
						className={ `cfmb-button-group__button${
							checked ? ' is-checked' : ''
						}` }
						onClick={ () => pick( choice ) }
						onKeyDown={ ( event ) => onKeyDown( event, index ) }
					>
						{ choice.label }
					</button>
				);
			} ) }
		</div>
	);
}
