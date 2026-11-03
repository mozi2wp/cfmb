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
	{ value: 'text', label: __( 'Text', 'custom-fields-meta-box' ) },
	{ value: 'media', label: __( 'Media', 'custom-fields-meta-box' ) },
	{ value: 'design', label: __( 'Design', 'custom-fields-meta-box' ) },
	{ value: 'widgets', label: __( 'Widgets', 'custom-fields-meta-box' ) },
	{ value: 'theme', label: __( 'Theme', 'custom-fields-meta-box' ) },
	{ value: 'embed', label: __( 'Embeds', 'custom-fields-meta-box' ) },
];

const SUPPORTS = () => [
	{ name: 'align', label: __( 'Alignment (wide, full width…)', 'custom-fields-meta-box' ) },
	{ name: 'anchor', label: __( 'HTML anchor', 'custom-fields-meta-box' ) },
	{ name: 'color_text', label: __( 'Text color', 'custom-fields-meta-box' ) },
	{ name: 'color_background', label: __( 'Background color', 'custom-fields-meta-box' ) },
	{ name: 'color_link', label: __( 'Link color', 'custom-fields-meta-box' ) },
	{ name: 'spacing_padding', label: __( 'Padding', 'custom-fields-meta-box' ) },
	{ name: 'spacing_margin', label: __( 'Margin', 'custom-fields-meta-box' ) },
	{ name: 'typography_font_size', label: __( 'Font size', 'custom-fields-meta-box' ) },
	{ name: 'typography_line_height', label: __( 'Line height', 'custom-fields-meta-box' ) },
	{ name: 'multiple', label: __( 'Can be used more than once in a post', 'custom-fields-meta-box' ) },
	{ name: 'inserter', label: __( 'Show in the block inserter', 'custom-fields-meta-box' ) },
];

const ALIGNMENTS = () => [
	{ value: 'left', label: __( 'Left', 'custom-fields-meta-box' ) },
	{ value: 'center', label: __( 'Center', 'custom-fields-meta-box' ) },
	{ value: 'right', label: __( 'Right', 'custom-fields-meta-box' ) },
	{ value: 'wide', label: __( 'Wide', 'custom-fields-meta-box' ) },
	{ value: 'full', label: __( 'Full width', 'custom-fields-meta-box' ) },
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
						label={ __( 'Label', 'custom-fields-meta-box' ) }
						value={ item[ labelKey ] || '' }
						onChange={ ( value ) => update( index, { [ labelKey ]: value, name: item.nameEdited ? item.name : blockSlug( value ) } ) }
					/>
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'Name', 'custom-fields-meta-box' ) }
						value={ item.name || '' }
						onChange={ ( value ) => update( index, { name: blockSlug( value ) || value.toLowerCase(), nameEdited: true } ) }
					/>
					{ withDescription && (
						<TextControl
							{ ...common }
							__next40pxDefaultSize
							label={ __( 'Description', 'custom-fields-meta-box' ) }
							value={ item.description || '' }
							onChange={ ( value ) => update( index, { description: value } ) }
						/>
					) }
					<CheckboxControl
						{ ...common }
						label={ __( 'Default', 'custom-fields-meta-box' ) }
						checked={ !! item.isDefault }
						onChange={ ( value ) => onChange( items.map( ( other, position ) => ( { ...other, isDefault: position === index ? value : value ? false : other.isDefault } ) ) ) }
					/>
					<Button variant="tertiary" isDestructive onClick={ () => remove( index ) }>
						{ __( 'Remove', 'custom-fields-meta-box' ) }
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
					label={ __( 'Use as a Field Block', 'custom-fields-meta-box' ) }
					help={ __( 'Adds a block to the block editor. Editors fill in this group\'s fields in the block, and a PHP template in your theme shows them.', 'custom-fields-meta-box' ) }
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
				label={ __( 'Use as a Field Block', 'custom-fields-meta-box' ) }
				checked
				onChange={ () => set( { enabled: false } ) }
			/>

			<div className="cfmb-block-settings__grid">
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Block name', 'custom-fields-meta-box' ) }
					help={
						name
							? sprintf(
									/* translators: 1: block name, 2: template file name */
									__( 'Saved in content as cfmb/%1$s. Template: cfmb-blocks/%2$s.php in your theme.', 'custom-fields-meta-box' ),
									name,
									name
							  )
							: __( 'Lowercase letters, numbers and dashes, starting with a letter.', 'custom-fields-meta-box' )
					}
					value={ block.name }
					placeholder={ suggested }
					onChange={ ( value ) => set( { name: value.toLowerCase().replace( /[^a-z0-9-]/g, '' ) } ) }
				/>
				{ savedName && name !== savedName && (
					<Notice status="warning" isDismissible={ false } className="cfmb-block-settings__rename">
						{ sprintf(
							/* translators: %s: old block name */
							__( 'Blocks already added as cfmb/%s will stop working until you change the name back.', 'custom-fields-meta-box' ),
							savedName
						) }
					</Notice>
				) }
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Title in the inserter', 'custom-fields-meta-box' ) }
					value={ block.title }
					placeholder={ group.title }
					onChange={ ( value ) => set( { title: value } ) }
				/>
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Category', 'custom-fields-meta-box' ) }
					value={ block.category }
					options={ CATEGORIES() }
					onChange={ ( value ) => set( { category: value } ) }
				/>
				<div className="cfmb-block-settings__icon">
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'Icon', 'custom-fields-meta-box' ) }
						help={ __( 'A Dashicon name, such as format-image or star-filled.', 'custom-fields-meta-box' ) }
						value={ block.icon }
						onChange={ ( value ) => set( { icon: value.toLowerCase().replace( /^dashicons-/, '' ).replace( /[^a-z0-9-]/g, '' ) } ) }
					/>
					<Dashicon icon={ block.icon || 'block-default' } className="cfmb-block-settings__icon-preview" />
				</div>
			</div>

			<SelectControl
				{ ...common }
				__next40pxDefaultSize
				label={ __( 'In the editor, show', 'custom-fields-meta-box' ) }
				value={ block.mode }
				options={ [
					{ value: 'preview', label: __( 'A preview, with fields in the sidebar', 'custom-fields-meta-box' ) },
					{ value: 'edit', label: __( 'The fields, in the block', 'custom-fields-meta-box' ) },
				] }
				help={ __( 'Editors can switch between the two from the block toolbar.', 'custom-fields-meta-box' ) }
				onChange={ ( value ) => set( { mode: value } ) }
			/>

			<PanelBody title={ __( 'More settings', 'custom-fields-meta-box' ) } initialOpen={ false } className="cfmb-block-settings__more">
				<TextareaControl
					{ ...common }
					label={ __( 'Description', 'custom-fields-meta-box' ) }
					rows={ 2 }
					value={ block.description }
					onChange={ ( value ) => set( { description: value } ) }
				/>
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Search keywords', 'custom-fields-meta-box' ) }
					help={ __( 'Separate with commas.', 'custom-fields-meta-box' ) }
					value={ block.keywords.join( ', ' ) }
					onChange={ ( value ) => set( { keywords: value.split( ',' ).map( ( word ) => word.trim() ).filter( Boolean ) } ) }
				/>
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Template file', 'custom-fields-meta-box' ) }
					help={ __( 'Leave empty to use cfmb-blocks/{name}.php from your theme. A path is read from your theme folder; it must be a .php file in your theme or the plugins folder.', 'custom-fields-meta-box' ) }
					value={ block.template }
					placeholder={ name ? `cfmb-blocks/${ name }.php` : '' }
					onChange={ ( value ) => set( { template: value } ) }
				/>

				<fieldset className="cfmb-block-settings__list">
					<legend className="cfmb-block-settings__legend">{ __( 'Block features', 'custom-fields-meta-box' ) }</legend>
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
						<legend className="cfmb-block-settings__legend">{ __( 'Alignments offered', 'custom-fields-meta-box' ) }</legend>
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
					legend={ __( 'Styles', 'custom-fields-meta-box' ) }
					help={ __( 'Editors pick a style in the block sidebar. The block gets the class is-style-{name}.', 'custom-fields-meta-box' ) }
					addLabel={ __( 'Add style', 'custom-fields-meta-box' ) }
					onChange={ ( styles ) => set( { styles } ) }
				/>
				<NamedList
					items={ block.variations }
					labelKey="title"
					withDescription
					legend={ __( 'Variations', 'custom-fields-meta-box' ) }
					help={ __( 'Extra entries in the inserter. A block added from one gets the class is-variation-{name}.', 'custom-fields-meta-box' ) }
					addLabel={ __( 'Add variation', 'custom-fields-meta-box' ) }
					onChange={ ( variations ) => set( { variations } ) }
				/>
			</PanelBody>
		</div>
	);
}
