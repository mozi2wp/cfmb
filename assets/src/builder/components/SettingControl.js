/**
 * Renders one type-specific setting from its schema descriptor.
 */
import { useEffect, useState } from '@wordpress/element';
import { BaseControl, CheckboxControl, Notice, SelectControl, Spinner, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { formatChoiceLines, parseChoiceLines } from '../../shared/choices';
import { presetSources } from '../api';
import { storesValue } from '../config';
import { useBuilder } from '../context';

const common = { __nextHasNoMarginBottom: true };

function ChoicesControl( { setting, value, onChange } ) {
	// Keep the raw text while typing so lines aren't reformatted under the cursor.
	const [ text, setText ] = useState( () => formatChoiceLines( value ) );

	return (
		<TextareaControl
			{ ...common }
			label={ setting.label }
			help={ setting.help }
			value={ text }
			rows={ 5 }
			className="cfmb-setting cfmb-setting--choices"
			onChange={ ( next ) => {
				setText( next );
				onChange( parseChoiceLines( next ) );
			} }
		/>
	);
}

function CheckboxesControl( { setting, value, onChange, options } ) {
	const picked = Array.isArray( value ) ? value : [];
	const entries = options || Object.entries( setting.options || {} ).map( ( [ optionValue, label ] ) => ( { value: optionValue, label } ) );

	return (
		<BaseControl { ...common } label={ setting.label } help={ setting.help } className="cfmb-setting cfmb-setting--checkboxes">
			<div className="cfmb-setting__checkboxes">
				{ entries.map( ( option ) => (
					<CheckboxControl
						{ ...common }
						key={ option.value }
						label={ option.label }
						checked={ picked.includes( option.value ) }
						onChange={ ( checked ) => onChange( checked ? [ ...picked, option.value ] : picked.filter( ( item ) => item !== option.value ) ) }
					/>
				) ) }
			</div>
		</BaseControl>
	);
}

function usePresetGroups() {
	const [ state, setState ] = useState( { groups: null, error: '' } );
	useEffect( () => {
		presetSources()
			.then( ( groups ) => setState( { groups, error: '' } ) )
			.catch( ( error ) => setState( { groups: [], error: error.message || __( 'Field groups could not be loaded.', 'custom-fields-meta-box' ) } ) );
	}, [] );
	return state;
}

function PresetSourceControl( { setting, value, onChange } ) {
	const { groupKey } = useBuilder();
	const { groups, error } = usePresetGroups();

	if ( ! groups ) {
		return <Spinner />;
	}

	const options = groups.filter( ( group ) => group.key !== groupKey );

	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<SelectControl
				{ ...common }
				__next40pxDefaultSize
				label={ setting.label }
				help={ __( 'Its fields are inserted here and stay in sync with it. Tip: turn on "Use only as a preset" in that group\'s settings so it doesn\'t also show on its own.', 'custom-fields-meta-box' ) }
				value={ value || '' }
				options={ [
					{ value: '', label: __( '— Choose a field group —', 'custom-fields-meta-box' ) },
					...options.map( ( group ) => ( { value: group.key, label: group.settings?.preset_only ? `${ group.title } (${ __( 'preset', 'custom-fields-meta-box' ) })` : group.title } ) ),
				] }
				onChange={ onChange }
			/>
		</>
	);
}

function PresetFieldsControl( { setting, value, onChange, field } ) {
	const { groups } = usePresetGroups();
	const source = field.settings?.source;

	if ( ! source ) {
		return null;
	}
	if ( ! groups ) {
		return <Spinner />;
	}

	const group = groups.find( ( item ) => item.key === source );
	if ( ! group ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __( 'That field group no longer exists. Choose another one.', 'custom-fields-meta-box' ) }
			</Notice>
		);
	}

	const options = ( group.fields || [] ).map( ( item ) => ( { value: item.key, label: item.name ? `${ item.label || item.name } (${ item.name })` : item.label || item.type } ) );

	return <CheckboxesControl setting={ setting } value={ value } onChange={ onChange } options={ options } />;
}

export default function SettingControl( { setting, value, onChange, field } ) {
	const props = {
		...common,
		label: setting.label,
		help: setting.help,
		className: `cfmb-setting cfmb-setting--${ setting.control }`,
	};

	switch ( setting.control ) {
		case 'toggle':
			return <ToggleControl { ...props } checked={ !! value } onChange={ onChange } />;

		case 'select':
			return (
				<SelectControl
					{ ...props }
					__next40pxDefaultSize
					value={ value ?? setting.default }
					options={ Object.entries( setting.options || {} ).map( ( [ optionValue, label ] ) => ( { value: optionValue, label } ) ) }
					onChange={ onChange }
				/>
			);

		case 'choices':
			return <ChoicesControl setting={ setting } value={ value } onChange={ onChange } />;

		case 'checkboxes':
			return <CheckboxesControl setting={ setting } value={ value } onChange={ onChange } />;

		case 'subfield': {
			const subs = ( field?.fields || [] ).filter( ( item ) => storesValue( item.type ) );
			return (
				<SelectControl
					{ ...props }
					__next40pxDefaultSize
					value={ value || '' }
					options={ [ { value: '', label: __( 'First filled-in text field', 'custom-fields-meta-box' ) }, ...subs.map( ( item ) => ( { value: item.key, label: item.label || item.name } ) ) ] }
					onChange={ onChange }
				/>
			);
		}

		case 'preset_source':
			return <PresetSourceControl setting={ setting } value={ value } onChange={ onChange } />;

		case 'preset_fields':
			return <PresetFieldsControl setting={ setting } value={ value } onChange={ onChange } field={ field } />;

		case 'textarea':
		case 'html':
			return <TextareaControl { ...props } rows={ 3 } value={ value ?? '' } onChange={ onChange } />;

		case 'number':
			return <TextControl { ...props } __next40pxDefaultSize type="number" value={ value ?? '' } onChange={ onChange } />;

		default:
			return <TextControl { ...props } __next40pxDefaultSize value={ value ?? '' } onChange={ onChange } />;
	}
}
