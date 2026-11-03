/**
 * Ordered list of fields, with add, reorder, duplicate and delete. Used for
 * the group's own fields and, nested, for Group, Repeater and Stacks fields.
 * Fields can be dragged between lists.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { defaultSettings } from '../config';
import { newFieldKey, newLayoutKey } from '../../shared/keys';
import { snackbar } from '../notices';
import { useBuilder } from '../context';
import FieldRow from './FieldRow';
import { uiIcon } from '../../shared/ui-icons';

export function blankLayout() {
	return {
		key: newLayoutKey(),
		name: '',
		label: '',
		min: 0,
		max: 0,
		fields: [],
	};
}

export function blankField( type = 'text' ) {
	const field = {
		key: newFieldKey(),
		name: '',
		label: '',
		type,
		instructions: '',
		required: false,
		wrapper: { width: 0, class: '' },
		conditions: [],
		settings: defaultSettings( type ),
	};
	if ( type === 'group' || type === 'repeater' ) {
		field.fields = [];
	}
	if ( type === 'stacks' ) {
		field.layouts = [ blankLayout() ];
	}
	return field;
}

/**
 * Copy of a field with new keys at every depth.
 *
 * @param {Object} field Field.
 * @return {Object} Copy.
 */
export function copyField( field ) {
	const copy = {
		...JSON.parse( JSON.stringify( field ) ),
		key: newFieldKey(),
	};
	const map = {};
	const rekeyList = ( list ) => {
		const fresh = ( list || [] ).map( ( item ) => {
			const next = copyField( item );
			map[ item.key ] = next.key;
			return next;
		} );
		return fresh.map( ( item ) => ( {
			...item,
			conditions: ( item.conditions || [] ).map( ( set ) =>
				set.map( ( rule ) => ( {
					...rule,
					field: map[ rule.field ] || rule.field,
				} ) )
			),
		} ) );
	};
	if ( copy.fields ) {
		copy.fields = rekeyList( copy.fields );
	}
	if ( copy.layouts ) {
		copy.layouts = copy.layouts.map( ( layout ) => ( {
			...layout,
			key: newLayoutKey(),
			fields: rekeyList( layout.fields ),
		} ) );
	}
	return copy;
}

const move = ( list, from, to ) => {
	const next = [ ...list ];
	const [ item ] = next.splice( from, 1 );
	next.splice( to, 0, item );
	return next;
};

export default function FieldList( {
	fields,
	setFields,
	expanded,
	setExpanded,
	errorKey,
	listId = '',
	depth = 0,
	prefixLength = 0,
	emptyText,
} ) {
	const builder = useBuilder();
	const [ focusKey, setFocusKey ] = useState( '' );
	const [ over, setOver ] = useState( null );

	useEffect( () => {
		if ( focusKey ) {
			document
				.getElementById( `cfmb-field-label-${ focusKey }` )
				?.focus();
			setFocusKey( '' );
		}
	}, [ focusKey ] );

	const toggle = ( key ) =>
		setExpanded( ( current ) => {
			const next = new Set( current );
			if ( next.has( key ) ) {
				next.delete( key );
			} else {
				next.add( key );
			}
			return next;
		} );

	const add = () => {
		const field = blankField();
		setFields( ( current ) => [ ...current, field ] );
		setExpanded( ( current ) => new Set( current ).add( field.key ) );
		setFocusKey( field.key );
	};

	const updateField = ( key ) => ( patch ) =>
		setFields( ( current ) =>
			current.map( ( field ) =>
				field.key === key
					? {
							...field,
							...( typeof patch === 'function'
								? patch( field )
								: patch ),
					  }
					: field
			)
		);

	const moveField = ( index, step ) => {
		const target = index + step;
		if ( target < 0 || target >= fields.length ) {
			return;
		}
		setFields( ( current ) => move( current, index, target ) );
		speak(
			sprintf(
				/* translators: 1: field label, 2: new position */
				__( '%1$s moved to position %2$d.', 'custom-fields-meta-box' ),
				fields[ index ].label ||
					__( '(no label)', 'custom-fields-meta-box' ),
				target + 1
			)
		);
	};

	const duplicate = ( index ) => {
		const source = fields[ index ];
		const copy = {
			...copyField( source ),
			/* translators: %s: field label */
			label: source.label
				? sprintf(
						/* translators: %s: source label */ __(
							'%s (copy)',
							'custom-fields-meta-box'
						),
						source.label
				  )
				: '',
			name: source.name ? `${ source.name }_copy` : '',
		};
		setFields( ( current ) => {
			const at = current.findIndex(
				( field ) => field.key === source.key
			);
			const next = [ ...current ];
			next.splice( at + 1, 0, copy );
			return next;
		} );
		setExpanded( ( current ) => new Set( current ).add( copy.key ) );
		setFocusKey( copy.key );
	};

	const remove = ( index ) => {
		const removed = fields[ index ];
		setFields( ( current ) =>
			current.filter( ( field ) => field.key !== removed.key )
		);
		snackbar(
			/* translators: %s: field label */
			sprintf(
				/* translators: %s: value */ __(
					'"%s" removed. Save to make it final.',
					'custom-fields-meta-box'
				),
				removed.label || __( '(no label)', 'custom-fields-meta-box' )
			),
			[
				{
					label: __( 'Undo', 'custom-fields-meta-box' ),
					onClick: () =>
						setFields( ( current ) => {
							if (
								current.some(
									( field ) => field.key === removed.key
								)
							) {
								return current;
							}
							const next = [ ...current ];
							next.splice(
								Math.min( index, next.length ),
								0,
								removed
							);
							return next;
						} ),
				},
			]
		);
	};

	const dropAt = ( target ) => {
		const dragging = builder.dragRef.current;
		if ( ! dragging ) {
			return;
		}
		if ( dragging.listId === listId ) {
			let to = target;
			if ( dragging.index < to ) {
				to -= 1;
			}
			if ( to !== dragging.index ) {
				setFields( ( current ) => move( current, dragging.index, to ) );
			}
		} else {
			builder.moveField( dragging.key, listId, target );
		}
		builder.dragRef.current = null;
		setOver( null );
	};

	const dragHandlers = ( index ) => ( {
		handle: {
			onDragStart: ( event ) => {
				event.stopPropagation();
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData( 'text/plain', fields[ index ].key );
				// Drag the whole row, not just the handle.
				const row = event.currentTarget.closest( '.cfmb-field-row' );
				if ( row ) {
					event.dataTransfer.setDragImage( row, 20, 20 );
				}
				builder.dragRef.current = {
					key: fields[ index ].key,
					listId,
					index,
				};
				setOver( { index, after: false } );
			},
			onDragEnd: () => {
				builder.dragRef.current = null;
				setOver( null );
			},
		},
		row: {
			onDragOver: ( event ) => {
				// Rows of inner lists handle their own drops.
				if (
					! builder.dragRef.current ||
					event.target.closest( '.cfmb-field-list' ) !==
						event.currentTarget.closest( '.cfmb-field-list' )
				) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				const box = event.currentTarget.getBoundingClientRect();
				const after = event.clientY > box.top + box.height / 2;
				if ( over?.index !== index || over?.after !== after ) {
					setOver( { index, after } );
				}
			},
			onDragLeave: ( event ) => {
				if ( ! event.currentTarget.contains( event.relatedTarget ) ) {
					setOver( ( current ) =>
						current?.index === index ? null : current
					);
				}
			},
			onDrop: ( event ) => {
				if (
					! builder.dragRef.current ||
					event.target.closest( '.cfmb-field-list' ) !==
						event.currentTarget.closest( '.cfmb-field-list' )
				) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				const box = event.currentTarget.getBoundingClientRect();
				dropAt(
					event.clientY > box.top + box.height / 2 ? index + 1 : index
				);
			},
		},
	} );

	const dragStateFor = ( index ) => {
		const dragging = builder.dragRef.current;
		if (
			dragging &&
			dragging.listId === listId &&
			dragging.index === index
		) {
			return 'dragging';
		}
		if ( over?.index === index ) {
			return over.after ? 'over-after' : 'over-before';
		}
		return '';
	};

	return (
		<div
			className={ `cfmb-field-list${
				depth > 0 ? ' cfmb-field-list--nested' : ''
			}` }
		>
			{ fields.length === 0 ? (
				<div
					className={ `cfmb-field-list__empty${
						over ? ' is-drop-target' : ''
					}` }
					onDragOver={ ( event ) => {
						if ( builder.dragRef.current ) {
							event.preventDefault();
							event.stopPropagation();
							setOver( { index: 0, after: false } );
						}
					} }
					onDragLeave={ () => setOver( null ) }
					onDrop={ ( event ) => {
						event.preventDefault();
						event.stopPropagation();
						dropAt( 0 );
					} }
				>
					<p>
						{ emptyText ||
							__(
								'No fields yet. Add the first one, like "Subtitle" or "Price".',
								'custom-fields-meta-box'
							) }
					</p>
				</div>
			) : (
				<ol className="cfmb-field-list__items">
					{ fields.map( ( field, index ) => (
						<FieldRow
							key={ field.key }
							field={ field }
							index={ index }
							count={ fields.length }
							siblings={ fields }
							listId={ listId }
							depth={ depth }
							prefixLength={ prefixLength }
							expanded={ expanded }
							setExpanded={ setExpanded }
							errorKey={ errorKey }
							isOpen={ expanded.has( field.key ) }
							hasError={ errorKey === field.key }
							dragState={ dragStateFor( index ) }
							onToggle={ () => toggle( field.key ) }
							updateField={ updateField( field.key ) }
							onMove={ ( step ) => moveField( index, step ) }
							onDuplicate={ () => duplicate( index ) }
							onRemove={ () => remove( index ) }
							dragHandlers={ dragHandlers( index ) }
						/>
					) ) }
				</ol>
			) }
			<div className="cfmb-field-list__add">
				<Button
					variant={ depth > 0 ? 'tertiary' : 'secondary' }
					icon={ uiIcon( 'plus-alt2' ) }
					onClick={ add }
				>
					{ depth > 0
						? __( 'Add sub-field', 'custom-fields-meta-box' )
						: __( 'Add field', 'custom-fields-meta-box' ) }
				</Button>
			</div>
		</div>
	);
}
