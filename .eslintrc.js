/**
 * ESLint: the @wordpress/scripts rules, with a few house-style choices.
 */
module.exports = {
	root: true,
	extends: [ require.resolve( '@wordpress/scripts/config/.eslintrc.js' ) ],
	rules: {
		// Short nested ternaries are used for labels and class names on purpose.
		'no-nested-ternary': 'off',
		// Values are read once at the top of a function, before early returns.
		'@wordpress/no-unused-vars-before-return': 'off',
		// Destructured props are described by the @param for the props object.
		'jsdoc/require-param': [ 'error', { checkDestructured: false } ],
		'jsdoc/check-param-names': [ 'error', { checkDestructured: false } ],
		'jsdoc/check-line-alignment': 'off',
		// A label that wraps its input is associated with it.
		'jsx-a11y/label-has-associated-control': [ 'error', { assert: 'either', depth: 5 } ],
		// REST and settings keys come from PHP in snake_case.
		camelcase: [ 'error', { properties: 'never', ignoreDestructuring: true } ],
	},
};
