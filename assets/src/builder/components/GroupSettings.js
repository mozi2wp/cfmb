import {
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import config from '../config';

const common = { __nextHasNoMarginBottom: true };

export default function GroupSettings( { group, onChange } ) {
	const settings = group.settings || {};
	const setSetting = ( name, value ) =>
		onChange( { settings: { ...settings, [ name ]: value } } );

	return (
		<div className="cfmb-group-settings">
			<ToggleControl
				{ ...common }
				label={ __( 'Active', 'custom-fields-meta-box' ) }
				help={ __(
					'Inactive groups keep their fields and values but are not shown on any screen.',
					'custom-fields-meta-box'
				) }
				checked={ !! group.active }
				onChange={ ( active ) => onChange( { active } ) }
			/>
			<ToggleControl
				{ ...common }
				label={ __( 'Use only as a preset', 'custom-fields-meta-box' ) }
				help={ __(
					'The group is not shown on any screen by itself. Insert it into other groups with a Preset field.',
					'custom-fields-meta-box'
				) }
				checked={ !! settings.preset_only }
				onChange={ ( value ) => setSetting( 'preset_only', value ) }
			/>
			<SelectControl
				{ ...common }
				__next40pxDefaultSize
				label={ __(
					'Position on post screens',
					'custom-fields-meta-box'
				) }
				value={ settings.position || 'normal' }
				help={
					settings.position === 'product_data'
						? __(
								'On product screens the group is a tab in the Product data box. Elsewhere it shows below the content.',
								'custom-fields-meta-box'
						  )
						: undefined
				}
				options={ [
					{
						value: 'high',
						label: __(
							'Top of the main column',
							'custom-fields-meta-box'
						),
					},
					{
						value: 'normal',
						label: __(
							'Below the content',
							'custom-fields-meta-box'
						),
					},
					{
						value: 'side',
						label: __( 'Sidebar', 'custom-fields-meta-box' ),
					},
					...( config.integrations?.woocommerce ||
					settings.position === 'product_data'
						? [
								{
									value: 'product_data',
									label: __(
										'Tab in the WooCommerce Product data box',
										'custom-fields-meta-box'
									),
								},
						  ]
						: [] ),
				] }
				onChange={ ( value ) => setSetting( 'position', value ) }
			/>
			<SelectControl
				{ ...common }
				__next40pxDefaultSize
				label={ __( 'Labels', 'custom-fields-meta-box' ) }
				value={ settings.label_placement || 'top' }
				options={ [
					{
						value: 'top',
						label: __( 'Above fields', 'custom-fields-meta-box' ),
					},
					{
						value: 'left',
						label: __( 'Beside fields', 'custom-fields-meta-box' ),
					},
				] }
				onChange={ ( value ) => setSetting( 'label_placement', value ) }
			/>
			<ToggleControl
				{ ...common }
				label={ __( 'Show in REST API', 'custom-fields-meta-box' ) }
				help={ __(
					'Adds these values, read-only, under "cfmb" in REST responses for the posts, terms, users and comments this group shows on.',
					'custom-fields-meta-box'
				) }
				checked={ !! settings.show_in_rest }
				onChange={ ( value ) => setSetting( 'show_in_rest', value ) }
			/>
			{ ( config.integrations?.graphql || settings.graphql?.enabled ) && (
				<>
					<ToggleControl
						{ ...common }
						label={ __(
							'Show in GraphQL',
							'custom-fields-meta-box'
						) }
						help={ __(
							'Adds these values to the WPGraphQL schema for the post types, taxonomies, users and options pages this group shows on.',
							'custom-fields-meta-box'
						) }
						checked={ !! settings.graphql?.enabled }
						onChange={ ( enabled ) =>
							setSetting(
								'graphql',
								enabled
									? {
											enabled: true,
											name: settings.graphql?.name || '',
									  }
									: undefined
							)
						}
					/>
					{ settings.graphql?.enabled && (
						<TextControl
							{ ...common }
							__next40pxDefaultSize
							label={ __(
								'GraphQL field name',
								'custom-fields-meta-box'
							) }
							help={ __(
								'camelCase, such as bookDetails. Leave empty to build it from the title.',
								'custom-fields-meta-box'
							) }
							value={ settings.graphql?.name || '' }
							onChange={ ( name ) =>
								setSetting( 'graphql', {
									enabled: true,
									name: name.replace( /[^A-Za-z0-9]/g, '' ),
								} )
							}
						/>
					) }
				</>
			) }
			<TextareaControl
				{ ...common }
				label={ __( 'Description', 'custom-fields-meta-box' ) }
				help={ __(
					'Shown to editors above the fields.',
					'custom-fields-meta-box'
				) }
				rows={ 2 }
				value={ settings.description || '' }
				onChange={ ( value ) => setSetting( 'description', value ) }
			/>
			<TextControl
				{ ...common }
				__next40pxDefaultSize
				type="number"
				label={ __( 'Order', 'custom-fields-meta-box' ) }
				help={ __(
					'Groups with lower numbers show first when several share a screen.',
					'custom-fields-meta-box'
				) }
				value={ group.order ?? 0 }
				onChange={ ( value ) =>
					onChange( { order: Number( value ) || 0 } )
				}
			/>
		</div>
	);
}
