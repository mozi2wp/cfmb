/**
 * Playwright config for end-to-end tests against wp-env (npm run env start).
 */
const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = {
	...baseConfig,
	testDir: __dirname,
};
