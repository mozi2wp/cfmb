/**
 * One field in the builder list: a summary row that expands into its
 * settings, and for Group, Repeater and Stacks fields, their own fields.
 */
import { useState } from '@wordpress/element';
import { Button, Dashicon, DropdownMenu, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { typesByName } from '../config';
import { useBuilder } from '../context';
import { childPrefix, holdsFields, keyLength, listInside } from '../tree';
import ConfirmModal from './ConfirmModal';
import FieldList from './FieldList';
import FieldSettings from './FieldSettings';
import LayoutsEditor from './LayoutsEditor';

function MoveModal( { field, listId, onClose } ) {
	const builder = useBuilder();
	const options = builder.lists().filter( ( list ) => list.id !== listId && ! listInside( field, list.id ) );
	const [ target, setTarget ] = useState( options[ 0 ]?.id ?? '' );

	return (
		<ConfirmModal
			title={
				/* translators: %s: field label */
				sprintf( __( 'Move "%s"', 'custom-fields-meta-box' ), field.label || __( '(no label)', 'custom-fields-meta-box' ) )
			}
			confirmLabel={ __( 'Move', 'custom-fields-meta-box' ) }
			onConfirm={ () => {
				if ( options.length ) {
					builder.moveField( field.key, target, Number.MAX_SAFE_INTEGER );
				}
				onClose();
			} }
			onCancel={ onClose }
		>
			{ options.length ? (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Move to', 'custom-fields-meta-box' ) }
						value={ target }
						options={ options.map( ( list ) => ( { value: list.id, label: list.label } ) ) }
						onChange={ setTarget }
					/>
					<p className="description">
						{ __( 'The field goes to the end of that list. Values already saved under its old place are not moved.', 'custom-fields-meta-box' ) }
					</p>
				</>
			) : (
				<p>{ __( 'There is nowhere else to move this field. Add a Group, Repeater or Stacks field first.', 'custom-fields-meta-box' ) }</p>
			) }
		</ConfirmModal>
	);
}

export default function FieldRow( {
	field,
	index,
	count,
	siblings,
	listId,
	depth,
	prefixLength = 0,
	expanded,
	setExpanded,
	errorKey,
	isOpen,
	hasError,
	dragState,
	onToggle,
	updateField,
	onMove,
	onDuplicate,
	onRemove,
	dragHandlers,
} ) {
	const [ moving, setMoving ] = useState( false );
	const type = typesByName[ field.type ];
	const bodyId = `cfmb-field-body-${ field.key }`;
	const label = field.label || __( '(no label)', 'custom-fields-meta-box' );
	const childCount = holdsFields( field )
		? ( field.fields || [] ).length
		: ( field.layouts || [] ).reduce( ( total, layout ) => total + ( layout.fields || [] ).length, 0 );

	const classes = [
		'cfmb-field-row',
		isOpen ? 'is-open' : '',
		hasError ? 'has-error' : '',
		dragState === 'dragging' ? 'is-dragging' : '',
		dragState === 'over-before' ? 'is-drop-before' : '',
		dragState === 'over-after' ? 'is-drop-after' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<li className={ classes } data-field-key={ field.key } { ...dragHandlers.row }>
			<div className="cfmb-field-row__header">
				<span
					className="cfmb-field-row__handle"
					draggable
					title={ __( 'Drag to reorder', 'custom-fields-meta-box' ) }
					aria-hidden="true"
					{ ...dragHandlers.handle }
				>
					<Dashicon icon="menu" />
				</span>
				<button type="button" className="cfmb-field-row__toggle" aria-expanded={ isOpen } aria-controls={ bodyId } onClick={ onToggle }>
					<span className="cfmb-field-row__order">{ index + 1 }</span>
					<span className="cfmb-field-row__label">
						{ label }
						{ field.required && (
							<span className="cfmb-field-row__required" aria-label={ __( 'required', 'custom-fields-meta-box' ) }>
								{ ' *' }
							</span>
						) }
						{ field.conditions?.length > 0 && <Dashicon icon="randomize" className="cfmb-field-row__flag" /> }
					</span>
					{ field.name && <code className="cfmb-field-row__name">{ field.name }</code> }
					{ ( holdsFields( field ) || field.type === 'stacks' ) && (
						<span className="cfmb-field-row__count">
							{ sprintf(
								/* translators: %d: number of sub-fields */
								__( '%d inside', 'custom-fields-meta-box' ),
								childCount
							) }
						</span>
					) }
					<span className="cfmb-field-row__type">{ type?.label || field.type }</span>
					<Dashicon icon={ isOpen ? 'arrow-up-alt2' : 'arrow-down-alt2' } className="cfmb-field-row__chevron" />
				</button>
				<DropdownMenu
					icon="ellipsis"
					label={
						/* translators: %s: field label */
						sprintf( __( 'Actions for %s', 'custom-fields-meta-box' ), label )
					}
					controls={ [
						{ title: __( 'Move up', 'custom-fields-meta-box' ), icon: 'arrow-up-alt', isDisabled: index === 0, onClick: () => onMove( -1 ) },
						{ title: __( 'Move down', 'custom-fields-meta-box' ), icon: 'arrow-down-alt', isDisabled: index === count - 1, onClick: () => onMove( 1 ) },
						{ title: __( 'Move to…', 'custom-fields-meta-box' ), icon: 'migrate', onClick: () => setMoving( true ) },
						{ title: __( 'Duplicate', 'custom-fields-meta-box' ), icon: 'admin-page', onClick: onDuplicate },
						{ title: __( 'Delete', 'custom-fields-meta-box' ), icon: 'trash', onClick: onRemove },
					] }
				/>
			</div>
			{ isOpen && (
				<div className="cfmb-field-row__body" id={ bodyId }>
					<FieldSettings field={ field } siblings={ siblings } updateField={ updateField } keyLength={ keyLength( prefixLength, field ) } topLevel={ ! listId } />
					{ holdsFields( field ) && (
						<div className="cfmb-field-row__children">
							<h3 className="cfmb-field-row__children-title">
								{ field.type === 'repeater' ? __( 'Fields in each row', 'custom-fields-meta-box' ) : __( 'Fields in this group', 'custom-fields-meta-box' ) }
							</h3>
							<FieldList
								fields={ field.fields || [] }
								setFields={ ( change ) => updateField( ( current ) => ( { fields: typeof change === 'function' ? change( current.fields || [] ) : change } ) ) }
								expanded={ expanded }
								setExpanded={ setExpanded }
								errorKey={ errorKey }
								listId={ field.key }
								depth={ depth + 1 }
								prefixLength={ childPrefix( prefixLength, field ) }
								emptyText={ __( 'No sub-fields yet. Add one, or drag a field here.', 'custom-fields-meta-box' ) }
							/>
						</div>
					) }
					{ field.type === 'stacks' && (
						<LayoutsEditor
							field={ field }
							updateField={ updateField }
							expanded={ expanded }
							setExpanded={ setExpanded }
							errorKey={ errorKey }
							depth={ depth }
							prefixLength={ childPrefix( prefixLength, field ) }
						/>
					) }
					<div className="cfmb-field-row__footer">
						<Button variant="secondary" size="compact" onClick={ onToggle }>
							{ __( 'Close field', 'custom-fields-meta-box' ) }
						</Button>
					</div>
				</div>
			) }
			{ moving && <MoveModal field={ field } listId={ listId } onClose={ () => setMoving( false ) } /> }
		</li>
	);
}
