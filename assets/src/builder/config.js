/**
 * Data printed by Builder_Screen::config().
 */
const config = {
	view: 'groups',
	pages: {},
	contentKinds: {},
	choiceTypes: [],
	fieldTypes: [],
	categories: {},
	locationRules: [],
	conditionOperators: [],
	defaultLocation: [],
	integrations: {},
	...( window.cfmbBuilder || {} ),
};

export default config;

export const typesByName = Object.fromEntries( config.fieldTypes.map( ( type ) => [ type.name, type ] ) );
export const rulesByParam = Object.fromEntries( config.locationRules.map( ( rule ) => [ rule.param, rule ] ) );

/**
 * Default settings for a type, from its settings schema.
 *
 * @param {string} typeName Type key.
 * @return {Object} Settings.
 */
export function defaultSettings( typeName ) {
	const type = typesByName[ typeName ];
	const settings = {};
	( type?.settings || [] ).forEach( ( setting ) => {
		settings[ setting.name ] = setting.default;
	} );
	return settings;
}

export function storesValue( typeName ) {
	return typesByName[ typeName ]?.storesValue !== false;
}

/**
 * Whether a field has a name: anything that stores a value, and presets
 * with prefixed names, which store their fields under it.
 *
 * @param {Object} field Field definition.
 * @return {boolean} Has a name.
 */
export function hasName( field ) {
	return storesValue( field.type ) || ( field.type === 'preset' && field.settings?.naming === 'prefixed' );
}
