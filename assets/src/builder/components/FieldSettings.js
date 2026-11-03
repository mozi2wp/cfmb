/**
 * Settings panel of one field in the builder.
 */
import { Notice, PanelBody, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import config, { defaultSettings, hasName, storesValue, typesByName } from '../config';
import { newLayoutKey, toFieldName } from '../../shared/keys';
import SettingControl from './SettingControl';
import ConditionsEditor from './ConditionsEditor';
import { defaultTranslation, nextIntegrations, readIntegrations } from '../integrations';

const common = { __nextHasNoMarginBottom: true };

function TypeSelect( { value, onChange, id } ) {
	const groups = Object.entries( config.categories )
		.map( ( [ category, label ] ) => ( {
			label,
			types: config.fieldTypes.filter( ( type ) => type.category === category ),
		} ) )
		.filter( ( group ) => group.types.length );

	return (
		<SelectControl { ...common } __next40pxDefaultSize id={ id } label={ __( 'Type', 'custom-fields-meta-box' ) } value={ value } onChange={ onChange }>
			{ groups.map( ( group ) => (
				<optgroup key={ group.label } label={ group.label }>
					{ group.types.map( ( type ) => (
						<option key={ type.name } value={ type.name }>
							{ type.label }
						</option>
					) ) }
				</optgroup>
			) ) }
		</SelectControl>
	);
}

// Types whose value is one meta row per item, or several parts; they can't sort.
const LIST_TYPES = [ 'checkbox', 'gallery', 'relationship' ];
const COMPOUND_TYPES = [ 'link', 'map' ];
const STRUCTURE_TYPES = [ 'group', 'repeater', 'stacks', 'preset' ];

function isSingleValue( field ) {
	if ( COMPOUND_TYPES.includes( field.type ) || LIST_TYPES.includes( field.type ) ) {
		return false;
	}
	return ! field.settings?.multiple;
}

/**
 * Column, sorting and filter options for list screens. Top-level fields only.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.field       Field.
 * @param {Function} props.updateField Update callback.
 * @return {Element} Controls.
 */
function ListScreenSettings( { field, updateField } ) {
	const admin = { column: false, sortable: false, filter: '', ...( field.admin || {} ) };
	const set = ( change ) => updateField( ( current ) => ( { admin: { column: false, sortable: false, filter: '', ...( current.admin || {} ), ...change } } ) );
	const single = isSingleValue( field );
	const hasChoices = ( config.choiceTypes || [] ).includes( field.type );
	const filterOptions = [
		{ value: '', label: __( 'No filter', 'custom-fields-meta-box' ) },
		...( hasChoices ? [ { value: 'choices', label: __( 'Dropdown of choices', 'custom-fields-meta-box' ) } ] : [] ),
		...( COMPOUND_TYPES.includes( field.type ) ? [] : [ { value: 'search', label: __( 'Search box', 'custom-fields-meta-box' ) } ] ),
	];

	return (
		<fieldset className="cfmb-field-settings__list-screens">
			<legend>{ __( 'List screens', 'custom-fields-meta-box' ) }</legend>
			<ToggleControl
				{ ...common }
				label={ __( 'Show as a column', 'custom-fields-meta-box' ) }
				help={ __( 'On the list of posts, terms or users this group shows on.', 'custom-fields-meta-box' ) }
				checked={ admin.column }
				onChange={ ( column ) => set( { column, ...( column ? {} : { sortable: false } ) } ) }
			/>
			{ admin.column && (
				<ToggleControl
					{ ...common }
					label={ __( 'Sortable', 'custom-fields-meta-box' ) }
					help={ single ? __( 'Click the column heading to sort by this field.', 'custom-fields-meta-box' ) : __( 'Only fields that hold one value can be sorted.', 'custom-fields-meta-box' ) }
					checked={ single && admin.sortable }
					disabled={ ! single }
					onChange={ ( sortable ) => set( { sortable } ) }
				/>
			) }
			{ filterOptions.length > 1 && (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Filter', 'custom-fields-meta-box' ) }
					help={ __( 'Adds a filter above post and user lists. Term lists have no filter area.', 'custom-fields-meta-box' ) }
					value={ filterOptions.some( ( option ) => option.value === admin.filter ) ? admin.filter : '' }
					options={ filterOptions }
					onChange={ ( filter ) => set( { filter } ) }
				/>
			) }
		</fieldset>
	);
}

/**
 * Options for translation, SEO and GraphQL plugins. Each shows only while
 * its plugin is active.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.field       Field.
 * @param {Function} props.updateField Update callback.
 * @return {Element|null} Controls.
 */
function IntegrationSettings( { field, updateField } ) {
	const active = config.integrations || {};
	const structure = STRUCTURE_TYPES.includes( field.type );
	const showTranslation = active.translation;
	const showSeo = active.seo && ! structure && field.type !== 'password';
	const showGraphql = active.graphql && field.type !== 'password' && field.type !== 'preset';

	if ( ! showTranslation && ! showSeo && ! showGraphql ) {
		return null;
	}

	const options = readIntegrations( field );
	const set = ( change ) => updateField( ( current ) => ( { integrations: nextIntegrations( current, change ) } ) );
	const labels = {
		translate: __( 'Translate', 'custom-fields-meta-box' ),
		copy: __( 'Copy', 'custom-fields-meta-box' ),
		copy_once: __( 'Copy once', 'custom-fields-meta-box' ),
		ignore: __( 'Ignore', 'custom-fields-meta-box' ),
	};

	return (
		<fieldset className="cfmb-field-settings__integrations">
			<legend>{ __( 'Other plugins', 'custom-fields-meta-box' ) }</legend>
			{ showTranslation && (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					label={ __( 'Translation (WPML, Polylang)', 'custom-fields-meta-box' ) }
					help={
						structure
							? __( 'Sub-fields follow this unless they set their own. Translate lets each language have its own rows.', 'custom-fields-meta-box' )
							: __( 'Translate: each language has its own value. Copy: kept the same everywhere. Copy once: copied to new translations, then edited separately.', 'custom-fields-meta-box' )
					}
					value={ options.translation }
					options={ [
						{
							value: '',
							label: structure
								? __( 'Default (follow sub-fields)', 'custom-fields-meta-box' )
								: sprintf(
										/* translators: %s: translation mode such as Translate or Copy */
										__( 'Default (%s)', 'custom-fields-meta-box' ),
										labels[ defaultTranslation( field.type ) ]
								  ),
						},
						{ value: 'translate', label: labels.translate },
						{ value: 'copy', label: labels.copy },
						{ value: 'copy_once', label: labels.copy_once },
						{ value: 'ignore', label: labels.ignore },
					] }
					onChange={ ( translation ) => set( { translation } ) }
				/>
			) }
			{ showSeo && (
				<ToggleControl
					{ ...common }
					label={ __( 'Include in SEO analysis', 'custom-fields-meta-box' ) }
					help={ __( 'Yoast SEO and Rank Math count this value as part of the content.', 'custom-fields-meta-box' ) }
					checked={ options.seo }
					onChange={ ( seo ) => set( { seo } ) }
				/>
			) }
			{ showGraphql && (
				<ToggleControl
					{ ...common }
					label={ __( 'Show in GraphQL', 'custom-fields-meta-box' ) }
					help={ __( 'Applies when the group has "Show in GraphQL" turned on.', 'custom-fields-meta-box' ) }
					checked={ options.graphql }
					onChange={ ( graphql ) => set( { graphql } ) }
				/>
			) }
		</fieldset>
	);
}

export default function FieldSettings( { field, siblings, updateField, keyLength = 0, topLevel = false } ) {
	const type = typesByName[ field.type ];
	const keepsValue = storesValue( field.type );
	const named = hasName( field );
	const schema = type?.settings || [];
	const basic = schema.filter( ( setting ) => ! setting.advanced );
	const advanced = schema.filter( ( setting ) => setting.advanced );
	const hasConditions = ( field.conditions || [] ).length > 0;

	const setSetting = ( name, value ) =>
		updateField( ( current ) => {
			const settings = { ...current.settings, [ name ]: value };
			// Picked fields belong to the old source group.
			if ( current.type === 'preset' && name === 'source' ) {
				settings.fields = [];
			}
			return { settings };
		} );

	// The name follows the label until someone edits the name directly.
	const onLabelChange = ( label ) =>
		updateField( ( current ) => {
			const follows = ! current.name || current.name === toFieldName( current.label, true );
			return follows && hasName( current ) ? { label, name: toFieldName( label, true ) } : { label };
		} );

	// Keep settings both types share, such as placeholder or default value.
	const onTypeChange = ( typeName ) =>
		updateField( ( current ) => {
			const next = defaultSettings( typeName );
			Object.keys( next ).forEach( ( name ) => {
				if ( current.settings && name in current.settings ) {
					next[ name ] = current.settings[ name ];
				}
			} );
			const shape = {};
			if ( ( typeName === 'group' || typeName === 'repeater' ) && ! Array.isArray( current.fields ) ) {
				shape.fields = [];
			}
			if ( typeName === 'stacks' && ! current.layouts?.length ) {
				shape.layouts = [ { key: newLayoutKey(), name: '', label: '', min: 0, max: 0, fields: [] } ];
			}
			return { type: typeName, settings: next, ...shape, ...( storesValue( typeName ) ? {} : { required: false } ) };
		} );

	return (
		<div className="cfmb-field-settings">
			<div className="cfmb-field-settings__grid">
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					id={ `cfmb-field-label-${ field.key }` }
					label={ __( 'Label', 'custom-fields-meta-box' ) }
					value={ field.label }
					onChange={ onLabelChange }
				/>
				{ named && (
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'Name', 'custom-fields-meta-box' ) }
						help={ __( 'The key the value is saved under. Lowercase letters, numbers and underscores.', 'custom-fields-meta-box' ) }
						value={ field.name }
						onChange={ ( name ) => updateField( { name: toFieldName( name ) } ) }
					/>
				) }
				<TypeSelect id={ `cfmb-field-type-${ field.key }` } value={ field.type } onChange={ onTypeChange } />
			</div>

			{ named && config.keyMaxLength && keyLength > config.keyMaxLength && (
				<Notice status="warning" isDismissible={ false } className="cfmb-field-settings__key-length">
					{ sprintf(
						/* translators: 1: key length, 2: maximum length */
						__( 'Saved keys for this field can reach %1$d characters; the limit is %2$d. Use a shorter name here or on the fields around it.', 'custom-fields-meta-box' ),
						keyLength,
						config.keyMaxLength
					) }
				</Notice>
			) }

			<TextareaControl
				{ ...common }
				label={ __( 'Instructions', 'custom-fields-meta-box' ) }
				help={ __( 'Shown under the field to help editors fill it in.', 'custom-fields-meta-box' ) }
				rows={ 2 }
				value={ field.instructions }
				onChange={ ( instructions ) => updateField( { instructions } ) }
			/>

			{ keepsValue && (
				<ToggleControl
					{ ...common }
					label={ __( 'Required', 'custom-fields-meta-box' ) }
					help={ __( 'Drafts can still be saved without it. Publishing needs a value.', 'custom-fields-meta-box' ) }
					checked={ !! field.required }
					onChange={ ( required ) => updateField( { required } ) }
				/>
			) }

			{ basic.map( ( setting ) => (
				<SettingControl
					key={ `${ field.type }-${ setting.name }` }
					field={ field }
					setting={ setting }
					value={ field.settings?.[ setting.name ] }
					onChange={ ( value ) => setSetting( setting.name, value ) }
				/>
			) ) }

			<ToggleControl
				{ ...common }
				label={ __( 'Show only when other fields match', 'custom-fields-meta-box' ) }
				checked={ hasConditions }
				onChange={ ( on ) =>
					updateField( () => {
						if ( ! on ) {
							return { conditions: [] };
						}
						const target = siblings.find( ( item ) => item.key !== field.key && storesValue( item.type ) );
						return { conditions: target ? [ [ { field: target.key, operator: '==', value: '' } ] ] : [] };
					} )
				}
			/>
			{ hasConditions && (
				<ConditionsEditor
					field={ field }
					siblings={ siblings }
					onChange={ ( change ) => updateField( ( current ) => ( { conditions: change( current.conditions ) } ) ) }
				/>
			) }

			<PanelBody title={ __( 'More settings', 'custom-fields-meta-box' ) } initialOpen={ false } className="cfmb-field-settings__more">
				{ advanced.map( ( setting ) => (
					<SettingControl
						key={ `${ field.type }-${ setting.name }` }
						field={ field }
						setting={ setting }
						value={ field.settings?.[ setting.name ] }
						onChange={ ( value ) => setSetting( setting.name, value ) }
					/>
				) ) }
				<div className="cfmb-field-settings__grid">
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						type="number"
						min={ 0 }
						max={ 100 }
						label={ __( 'Width (%)', 'custom-fields-meta-box' ) }
						help={ __( 'Place fields side by side, for example two at 50.', 'custom-fields-meta-box' ) }
						value={ field.wrapper?.width || '' }
						onChange={ ( width ) => updateField( ( current ) => ( { wrapper: { ...current.wrapper, width: Number( width ) || 0 } } ) ) }
					/>
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						label={ __( 'CSS class', 'custom-fields-meta-box' ) }
						value={ field.wrapper?.class || '' }
						onChange={ ( className ) => updateField( ( current ) => ( { wrapper: { ...current.wrapper, class: className } } ) ) }
					/>
				</div>
				{ keepsValue && ! STRUCTURE_TYPES.includes( field.type ) && topLevel && <ListScreenSettings field={ field } updateField={ updateField } /> }
				{ keepsValue && field.type !== 'preset' && (
					<ToggleControl
						{ ...common }
						label={ __( 'Include in the REST API', 'custom-fields-meta-box' ) }
						help={ __( 'Applies when the group has "Show in REST API" turned on.', 'custom-fields-meta-box' ) }
						checked={ field.rest !== false }
						onChange={ ( rest ) => updateField( { rest } ) }
					/>
				) }
				{ keepsValue && <IntegrationSettings field={ field } updateField={ updateField } /> }
				<p className="cfmb-field-settings__key">
					{ __( 'Field key:', 'custom-fields-meta-box' ) } <code>{ field.key }</code>
				</p>
			</PanelBody>
		</div>
	);
}
