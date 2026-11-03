/**
 * Layouts of a Stacks field: each has a name, label, row limits and fields.
 */
import { useState } from '@wordpress/element';
import { Button, DropdownMenu, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { toFieldName } from '../../shared/keys';
import { snackbar } from '../notices';
import FieldList, { blankLayout, copyField } from './FieldList';
import { uiIcon } from '../../shared/ui-icons';

const common = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

export default function LayoutsEditor( {
	field,
	updateField,
	expanded,
	setExpanded,
	errorKey,
	depth,
	prefixLength = 0,
} ) {
	const layouts = field.layouts || [];
	const [ open, setOpen ] = useState(
		() => new Set( layouts.length === 1 ? [ layouts[ 0 ].key ] : [] )
	);

	const setLayouts = ( change ) =>
		updateField( ( current ) => ( {
			layouts: change( current.layouts || [] ),
		} ) );
	const patchLayout = ( key, patch ) =>
		setLayouts( ( list ) =>
			list.map( ( layout ) =>
				layout.key === key
					? {
							...layout,
							...( typeof patch === 'function'
								? patch( layout )
								: patch ),
					  }
					: layout
			)
		);

	const toggle = ( key ) =>
		setOpen( ( current ) => {
			const next = new Set( current );
			if ( next.has( key ) ) {
				next.delete( key );
			} else {
				next.add( key );
			}
			return next;
		} );

	const add = () => {
		const layout = blankLayout();
		setLayouts( ( list ) => [ ...list, layout ] );
		setOpen( ( current ) => new Set( current ).add( layout.key ) );
		window.setTimeout(
			() =>
				document
					.getElementById( `cfmb-layout-label-${ layout.key }` )
					?.focus(),
			50
		);
	};

	const moveLayout = ( index, step ) => {
		const target = index + step;
		if ( target < 0 || target >= layouts.length ) {
			return;
		}
		setLayouts( ( list ) => {
			const next = [ ...list ];
			const [ item ] = next.splice( index, 1 );
			next.splice( target, 0, item );
			return next;
		} );
		/* translators: %d: new position */
		speak(
			sprintf(
				__( 'Layout moved to position %d.', 'custom-fields-meta-box' ),
				target + 1
			)
		);
	};

	const duplicate = ( index ) => {
		const source = layouts[ index ];
		const copy = {
			...copyField( { key: 'fld_tmp000', fields: source.fields || [] } ),
			key: blankLayout().key,
			/* translators: %s: layout label */
			label: source.label
				? sprintf(
						__( '%s (copy)', 'custom-fields-meta-box' ),
						source.label
				  )
				: '',
			name: source.name ? `${ source.name }_copy` : '',
			min: source.min,
			max: source.max,
		};
		setLayouts( ( list ) => {
			const next = [ ...list ];
			next.splice( index + 1, 0, copy );
			return next;
		} );
	};

	const remove = ( index ) => {
		const removed = layouts[ index ];
		setLayouts( ( list ) =>
			list.filter( ( layout ) => layout.key !== removed.key )
		);
		snackbar(
			/* translators: %s: layout label */
			sprintf(
				__(
					'Layout "%s" removed. Save to make it final.',
					'custom-fields-meta-box'
				),
				removed.label ||
					removed.name ||
					__( '(no label)', 'custom-fields-meta-box' )
			),
			[
				{
					label: __( 'Undo', 'custom-fields-meta-box' ),
					onClick: () =>
						setLayouts( ( list ) => {
							if (
								list.some(
									( layout ) => layout.key === removed.key
								)
							) {
								return list;
							}
							const next = [ ...list ];
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

	return (
		<div className="cfmb-layouts">
			<h3 className="cfmb-field-row__children-title">
				{ __( 'Layouts', 'custom-fields-meta-box' ) }
			</h3>
			<p className="description">
				{ __(
					'Editors stack these in any order. Each layout has its own fields.',
					'custom-fields-meta-box'
				) }
			</p>
			<ol className="cfmb-layouts__list">
				{ layouts.map( ( layout, index ) => {
					const isOpen = open.has( layout.key );
					const label =
						layout.label ||
						layout.name ||
						__( '(no label)', 'custom-fields-meta-box' );
					return (
						<li
							key={ layout.key }
							className={ `cfmb-layout${
								isOpen ? ' is-open' : ''
							}` }
						>
							<div className="cfmb-layout__header">
								<button
									type="button"
									className="cfmb-layout__toggle"
									aria-expanded={ isOpen }
									onClick={ () => toggle( layout.key ) }
								>
									<span className="cfmb-layout__label">
										{ label }
									</span>
									{ layout.name && (
										<code>{ layout.name }</code>
									) }
									<span className="cfmb-field-row__count">
										{ sprintf(
											/* translators: %d: number of fields */
											__(
												'%d fields',
												'custom-fields-meta-box'
											),
											( layout.fields || [] ).length
										) }
									</span>
								</button>
								<DropdownMenu
									icon={ uiIcon( 'ellipsis' ) }
									label={
										/* translators: %s: layout label */
										sprintf(
											__(
												'Actions for layout %s',
												'custom-fields-meta-box'
											),
											label
										)
									}
									controls={ [
										{
											title: __(
												'Move up',
												'custom-fields-meta-box'
											),
											icon: uiIcon( 'arrow-up-alt' ),
											isDisabled: index === 0,
											onClick: () =>
												moveLayout( index, -1 ),
										},
										{
											title: __(
												'Move down',
												'custom-fields-meta-box'
											),
											icon: uiIcon( 'arrow-down-alt' ),
											isDisabled:
												index === layouts.length - 1,
											onClick: () =>
												moveLayout( index, 1 ),
										},
										{
											title: __(
												'Duplicate',
												'custom-fields-meta-box'
											),
											icon: uiIcon( 'admin-page' ),
											onClick: () => duplicate( index ),
										},
										{
											title: __(
												'Delete',
												'custom-fields-meta-box'
											),
											icon: uiIcon( 'trash' ),
											onClick: () => remove( index ),
										},
									] }
								/>
							</div>
							{ isOpen && (
								<div className="cfmb-layout__body">
									<div className="cfmb-layout__settings">
										<TextControl
											{ ...common }
											id={ `cfmb-layout-label-${ layout.key }` }
											label={ __(
												'Label',
												'custom-fields-meta-box'
											) }
											value={ layout.label || '' }
											onChange={ ( value ) =>
												patchLayout(
													layout.key,
													( current ) => {
														const follows =
															! current.name ||
															current.name ===
																toFieldName(
																	current.label,
																	true
																);
														return follows
															? {
																	label: value,
																	name: toFieldName(
																		value,
																		true
																	),
															  }
															: { label: value };
													}
												)
											}
										/>
										<TextControl
											{ ...common }
											label={ __(
												'Name',
												'custom-fields-meta-box'
											) }
											help={ __(
												'Saved with each row, so templates can tell layouts apart.',
												'custom-fields-meta-box'
											) }
											value={ layout.name || '' }
											onChange={ ( value ) =>
												patchLayout( layout.key, {
													name: toFieldName( value ),
												} )
											}
										/>
										<TextControl
											{ ...common }
											type="number"
											min={ 0 }
											label={ __(
												'Minimum rows',
												'custom-fields-meta-box'
											) }
											value={ layout.min || '' }
											onChange={ ( value ) =>
												patchLayout( layout.key, {
													min: Number( value ) || 0,
												} )
											}
										/>
										<TextControl
											{ ...common }
											type="number"
											min={ 0 }
											label={ __(
												'Maximum rows',
												'custom-fields-meta-box'
											) }
											value={ layout.max || '' }
											onChange={ ( value ) =>
												patchLayout( layout.key, {
													max: Number( value ) || 0,
												} )
											}
										/>
									</div>
									<FieldList
										fields={ layout.fields || [] }
										setFields={ ( change ) =>
											patchLayout(
												layout.key,
												( current ) => ( {
													fields:
														typeof change ===
														'function'
															? change(
																	current.fields ||
																		[]
															  )
															: change,
												} )
											)
										}
										expanded={ expanded }
										setExpanded={ setExpanded }
										errorKey={ errorKey }
										listId={ `${ field.key }:${ layout.key }` }
										depth={ depth + 1 }
										prefixLength={ prefixLength }
										emptyText={ __(
											'No fields in this layout yet.',
											'custom-fields-meta-box'
										) }
									/>
								</div>
							) }
						</li>
					);
				} ) }
			</ol>
			<Button
				variant="secondary"
				size="compact"
				icon={ uiIcon( 'plus-alt2' ) }
				onClick={ add }
			>
				{ __( 'Add layout', 'custom-fields-meta-box' ) }
			</Button>
		</div>
	);
}
