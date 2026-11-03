/**
 * "Field Block" settings of a group: turn the group into a block, and its
 * block type settings. Basic settings first, the rest under "More settings".
 */
import {
	Button,
	CheckboxControl,
	Dashicon,
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import config from '../config';
import { blockSettings, blockSlug } from '../blockSettings';

const common = { __nextHasNoMarginBottom: true };

const CATEGORIES = () => [
	{ value: 'text', label: __( 'Text', 'cfmb' ) },
	{ value: 'media', label: __( 'Media', 'cfmb' ) },
	{ value: 'design', label: __( 'Design', 'cfmb' ) },
	{ value: 'widgets', label: __( 'Widgets', 'cfmb' ) },
	{ value: 'theme', label: __( 'Theme', 'cfmb' ) },
	{ value: 'embed', label: __( 'Embeds', 'cfmb' ) },
];

const SUPPORTS = () => [
	{ name: 'align', label: __( 'Alignment (wide, full width…)', 'cfmb' ) },
	{ name: 'anchor', label: __( 'HTML anchor', 'cfmb' ) },
	{ name: 'color_text', label: __( 'Text color', 'cfmb' ) },
	{ name: 'color_background', label: __( 'Background color', 'cfmb' ) },
	{ name: 'color_link', label: __( 'Link color', 'cfmb' ) },
	{ name: 'spacing_padding', label: __( 'Padding', 'cfmb' ) },
	{ name: 'spacing_margin', label: __( 'Margin', 'cfmb' ) },
	{ name: 'typography_font_size', label: __( 'Font size', 'cfmb' ) },
	{ name: 'typography_line_height', label: __( 'Line height', 'cfmb' ) },
	{ name: 'multiple', label: __( 'Can be used more than once in a post', 'cfmb' ) },
	{ name: 'inserter', label: __( 'Show in the block inserter', 'cfmb' ) },
];

const ALIGNMENTS = () => [
	{ value: 'left', label: __( 'Left', 'cfmb' ) },
	{ value: 'center', label: __( 'Center', 'cfmb' ) },
	{ value: 'right', label: __( 'Right', 'cfmb' ) },
	{ value: 'wide', label: __( 'Wide', 'cfmb' ) },
	{ value: 'full', label: __( 'Full width', 'cfmb' ) },
];

/**
 * Editable list of block styles or variations.
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.items    Items.
 * @param {string}   props.labelKey label (styles) or title (variations).
 * @param {Function} props.onChange Change handler.
 * @param {boolean}  props.withDescription Whether items have a description.
 * @param {string}   props.addLabel Add button label.
 * @param {string}   props.legend   List label.
 * @param {string}   props.help     Help text.
 */
function NamedList( { items, labelKey, onChange, withDescription, addLabel, legend, help } ) {
	const update = ( index, change ) => onChange( items.map( ( item, position ) => ( position === index ? { ...item, ...change } : item ) ) );
	const remove = ( index ) => onChange( items.filter( ( _, position ) => position !== index ) );

	return (
		<fieldset className="cfmb-block-settings__list">
			<legend className="cfmb-block-settings__legend">{ legend }</legend>
			<p className="cfmb-block-settings__help">{ help }</p>
			{ items.map( ( item, index ) => (
				<div className="cfmb-block-settings__item" key={ index }>
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'Label', 'cfmb' ) }
						value={ item[ labelKey ] || '' }
						onChange={ ( value ) => update( index, { [ labelKey ]: value, name: item.nameEdited ? item.name : blockSlug( value ) } ) }
					/>
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'Name', 'cfmb' ) }
						value={ item.name || '' }
						onChange={ ( value ) => update( index, { name: blockSlug( value ) || value.toLowerCase(), nameEdited: true } ) }
					/>
					{ withDescription && (
						<TextControl
							{ ...common }
							__next40pxDefaultSize
							label={ __( 'Description', 'cfmb' ) }
							value={ item.description || '' }
							onChange={ ( value ) => update( index, { description: value } ) }
						/>
					) }
					<CheckboxControl
						{ ...common }
						label={ __( 'Default', 'cfmb' ) }
						checked={ !! item.isDefault }
						onChange={ ( value ) => onChange( items.map( ( other, position ) => ( { ...other, isDefault: position === index ? value : value ? false : other.isDefault } ) ) ) }
					/>
					<Button variant="tertiary" isDestructive onClick={ () => remove( index ) }>
						{ __( 'Remove', 'cfmb' ) }
					</Button>
				</div>
			) ) }
			<Button variant="secondary" onClick={ () => onChange( [ ...items, { name: '', [ labelKey ]: '', isDefault: false } ] ) }>
				{ addLabel }
			</Button>
		</fieldset>
	);
}

export default function FieldBlockSettings( { group, onChange, savedName = '' } ) {
	const settings = group.settings || {};
	const block = blockSettings( settings.block );
	const set = ( change ) => onChange( { settings: { ...settings, block: { ...block, ...change } } } );
	const setSupport = ( name, value ) => set( { supports: { ...block.supports, [ name ]: value } } );
	const suggested = blockSlug( group.title );
	const name = block.name || suggested;

	if ( ! block.enabled ) {
		return (
			<div className="cfmb-block-settings">
				<ToggleControl
					{ ...common }
					label={ __( 'Use as a Field Block', 'cfmb' ) }
					help={ __( 'Adds a block to the block editor. Editors fill in this group\'s fields in the block, and a PHP template in your theme shows them.', 'cfmb' ) }
					checked={ false }
					onChange={ () => {
						const change = { settings: { ...settings, block: { ...block, enabled: true, name: block.name || suggested } } };
						// A new group starts on posts; as a block it usually belongs only to the block.
						if ( JSON.stringify( group.location ) === JSON.stringify( config.defaultLocation ) ) {
							change.location = [];
						}
						onChange( change );
					} }
				/>
			</div>
		);
	}

	return (
		<div className="cfmb-block-settings">
			<ToggleControl
				{ ...common }
				label={ __( 'Use as a Field Block', 'cfmb' ) }
				checked
				onChange={ () => set( { enabled: false } ) }
			/>

			<div className="cfmb-block-settings__grid">
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Block name', 'cfmb' ) }
					help={
						name
							? sprintf(
									/* translators: 1: block name, 2: template file name */
									__( 'Saved in content as cfmb/%1$s. Template: cfmb-blocks/%2$s.php in your theme.', 'cfmb' ),
									name,
									name
							  )
							: __( 'Lowercase letters, numbers and dashes, starting with a letter.', 'cfmb' )
					}
					value={ block.name }
					placeholder={ suggested }
					onChange={ ( value ) => set( { name: value.toLowerCase().replace( /[^a-z0-9-]/g, '' ) } ) }
				/>
				{ savedName && name !== savedName && (
					<Notice status="warning" isDismissible={ false } className="cfmb-block-settings__rename">
						{ sprintf(
							/* translators: %s: old block name */
							__( 'Blocks already added as cfmb/%s will stop working until you change the name back.', 'cfmb' ),
							savedName
						) }
					</Notice>
				) }
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Title in the inserter', 'cfmb' ) }
					value={ block.title }
					placeholder={ group.title }
					onChange={ ( value ) => set( { title: value } ) }
				/>
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Category', 'cfmb' ) }
					value={ block.category }
					options={ CATEGORIES() }
					onChange={ ( value ) => set( { category: value } ) }
				/>
				<div className="cfmb-block-settings__icon">
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'Icon', 'cfmb' ) }
						help={ __( 'A Dashicon name, such as format-image or star-filled.', 'cfmb' ) }
						value={ block.icon }
						onChange={ ( value ) => set( { icon: value.toLowerCase().replace( /^dashicons-/, '' ).replace( /[^a-z0-9-]/g, '' ) } ) }
					/>
					<Dashicon icon={ block.icon || 'block-default' } className="cfmb-block-settings__icon-preview" />
				</div>
			</div>

			<SelectControl
				{ ...common }
				__next40pxDefaultSize
				label={ __( 'In the editor, show', 'cfmb' ) }
				value={ block.mode }
				options={ [
					{ value: 'preview', label: __( 'A preview, with fields in the sidebar', 'cfmb' ) },
					{ value: 'edit', label: __( 'The fields, in the block', 'cfmb' ) },
				] }
				help={ __( 'Editors can switch between the two from the block toolbar.', 'cfmb' ) }
				onChange={ ( value ) => set( { mode: value } ) }
			/>

			<PanelBody title={ __( 'More settings', 'cfmb' ) } initialOpen={ false } className="cfmb-block-settings__more">
				<TextareaControl
					{ ...common }
					label={ __( 'Description', 'cfmb' ) }
					rows={ 2 }
					value={ block.description }
					onChange={ ( value ) => set( { description: value } ) }
				/>
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Search keywords', 'cfmb' ) }
					help={ __( 'Separate with commas.', 'cfmb' ) }
					value={ block.keywords.join( ', ' ) }
					onChange={ ( value ) => set( { keywords: value.split( ',' ).map( ( word ) => word.trim() ).filter( Boolean ) } ) }
				/>
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Template file', 'cfmb' ) }
					help={ __( 'Leave empty to use cfmb-blocks/{name}.php from your theme. A path is read from your theme folder; it must be a .php file in your theme or the plugins folder.', 'cfmb' ) }
					value={ block.template }
					placeholder={ name ? `cfmb-blocks/${ name }.php` : '' }
					onChange={ ( value ) => set( { template: value } ) }
				/>

				<fieldset className="cfmb-block-settings__list">
					<legend className="cfmb-block-settings__legend">{ __( 'Block features', 'cfmb' ) }</legend>
					<div className="cfmb-block-settings__checks">
						{ SUPPORTS().map( ( support ) => (
							<CheckboxControl
								{ ...common }
								key={ support.name }
								label={ support.label }
								checked={ !! block.supports[ support.name ] }
								onChange={ ( value ) => setSupport( support.name, value ) }
							/>
						) ) }
					</div>
				</fieldset>

				{ block.supports.align && (
					<fieldset className="cfmb-block-settings__list">
						<legend className="cfmb-block-settings__legend">{ __( 'Alignments offered', 'cfmb' ) }</legend>
						<div className="cfmb-block-settings__checks">
							{ ALIGNMENTS().map( ( alignment ) => (
								<CheckboxControl
									{ ...common }
									key={ alignment.value }
									label={ alignment.label }
									checked={ block.alignments.includes( alignment.value ) }
									onChange={ ( value ) =>
										set( {
											alignments: value
												? [ ...block.alignments, alignment.value ]
												: block.alignments.filter( ( item ) => item !== alignment.value ),
										} )
									}
								/>
							) ) }
						</div>
					</fieldset>
				) }

				<NamedList
					items={ block.styles }
					labelKey="label"
					legend={ __( 'Styles', 'cfmb' ) }
					help={ __( 'Editors pick a style in the block sidebar. The block gets the class is-style-{name}.', 'cfmb' ) }
					addLabel={ __( 'Add style', 'cfmb' ) }
					onChange={ ( styles ) => set( { styles } ) }
				/>
				<NamedList
					items={ block.variations }
					labelKey="title"
					withDescription
					legend={ __( 'Variations', 'cfmb' ) }
					help={ __( 'Extra entries in the inserter. A block added from one gets the class is-variation-{name}.', 'cfmb' ) }
					addLabel={ __( 'Add variation', 'cfmb' ) }
					onChange={ ( variations ) => set( { variations } ) }
				/>
			</PanelBody>
		</div>
	);
}
