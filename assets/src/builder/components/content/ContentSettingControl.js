/**
 * Controls for post type, taxonomy and options page settings. Shared
 * controls come from SettingControl; this adds tristate, slug, dashicon,
 * labels and free-form checkbox lists.
 */
import {
	BaseControl,
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SettingControl from '../SettingControl';
import { fillLabel } from './labels';

const common = { __nextHasNoMarginBottom: true };

function LabelsControl( { setting, value, onChange, singular, plural } ) {
	const labels = value && ! Array.isArray( value ) ? value : {};
	const entries = Object.entries( setting.labels || {} );

	return (
		// eslint-disable-next-line @wordpress/no-base-control-with-label-without-id -- A label for a group of controls; each control has its own label.
		<BaseControl
			{ ...common }
			label={ setting.label }
			help={ __(
				'Leave a label empty to build it from the singular and plural names.',
				'custom-fields-meta-box'
			) }
			className="cfmb-setting cfmb-setting--labels"
		>
			<div className="cfmb-labels">
				{ entries.map( ( [ key, [ label, template ] ] ) => (
					<TextControl
						{ ...common }
						__next40pxDefaultSize
						key={ key }
						label={ label }
						placeholder={ fillLabel(
							template,
							singular || plural,
							plural
						) }
						value={ labels[ key ] || '' }
						onChange={ ( text ) => {
							const next = { ...labels };
							if ( text ) {
								next[ key ] = text;
							} else {
								delete next[ key ];
							}
							onChange( next );
						} }
					/>
				) ) }
			</div>
		</BaseControl>
	);
}

function DashiconControl( { setting, value, onChange } ) {
	const icon = value || setting.default || '';
	const isDashicon = /^dashicons-[a-z0-9-]+$/.test( icon );

	return (
		<div className="cfmb-setting cfmb-setting--dashicon">
			<TextControl
				{ ...common }
				__next40pxDefaultSize
				label={ setting.label }
				help={ setting.help }
				value={ value ?? '' }
				placeholder={ setting.default }
				onChange={ onChange }
			/>
			<span className="cfmb-dashicon-preview" aria-hidden="true">
				{ isDashicon ? (
					<span className={ `dashicons ${ icon }` } />
				) : (
					icon && <img src={ icon } alt="" />
				) }
			</span>
		</div>
	);
}

function FreeCheckboxes( { setting, value, onChange } ) {
	const picked = Array.isArray( value ) ? value : [];
	const options = Object.entries( setting.options || {} ).map(
		( [ optionValue, label ] ) => ( { value: optionValue, label } )
	);

	// Keep picked items that aren't registered right now, such as a post type from a deactivated plugin.
	picked.forEach( ( item ) => {
		if ( ! options.some( ( option ) => option.value === item ) ) {
			options.push( {
				value: item,
				label: `${ item } ${ __(
					'(not registered)',
					'custom-fields-meta-box'
				) }`,
			} );
		}
	} );

	return (
		// eslint-disable-next-line @wordpress/no-base-control-with-label-without-id -- A label for a group of controls; each control has its own label.
		<BaseControl
			{ ...common }
			label={ setting.label }
			help={ setting.help }
			className="cfmb-setting cfmb-setting--checkboxes"
		>
			<div className="cfmb-setting__checkboxes">
				{ options.map( ( option ) => (
					<CheckboxControl
						{ ...common }
						key={ option.value }
						label={ option.label }
						checked={ picked.includes( option.value ) }
						onChange={ ( checked ) =>
							onChange(
								checked
									? [ ...picked, option.value ]
									: picked.filter(
											( item ) => item !== option.value
									  )
							)
						}
					/>
				) ) }
			</div>
		</BaseControl>
	);
}

export default function ContentSettingControl( {
	setting,
	value,
	onChange,
	singular,
	plural,
	disabled,
} ) {
	switch ( setting.control ) {
		case 'tristate':
			return (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					className="cfmb-setting cfmb-setting--tristate"
					label={ setting.label }
					help={ setting.help }
					disabled={ disabled }
					value={ value ?? '' }
					options={ Object.entries( setting.options || {} ).map(
						( [ optionValue, label ] ) => ( {
							value: optionValue,
							label,
						} )
					) }
					onChange={ onChange }
				/>
			);

		case 'slug':
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					className="cfmb-setting cfmb-setting--slug"
					label={ setting.label }
					help={ setting.help }
					disabled={ disabled }
					value={ value ?? '' }
					onChange={ ( text ) =>
						onChange(
							text.toLowerCase().replace( /[^a-z0-9_/-]+/g, '-' )
						)
					}
				/>
			);

		case 'dashicon':
			return (
				<DashiconControl
					setting={ setting }
					value={ value }
					onChange={ onChange }
				/>
			);

		case 'labels':
			return (
				<LabelsControl
					setting={ setting }
					value={ value }
					onChange={ onChange }
					singular={ singular }
					plural={ plural }
				/>
			);

		case 'checkboxes':
			if ( setting.free ) {
				return (
					<FreeCheckboxes
						setting={ setting }
						value={ value }
						onChange={ onChange }
					/>
				);
			}
			return (
				<SettingControl
					setting={ setting }
					value={ value }
					onChange={ onChange }
				/>
			);

		default:
			return (
				<SettingControl
					setting={ setting }
					value={ value }
					onChange={ onChange }
				/>
			);
	}
}
