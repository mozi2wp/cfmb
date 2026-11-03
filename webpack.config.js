/**
 * Bundles: the Field Group builder, the edit screen fields, the map
 * (Leaflet, loaded only where a map field uses a map service), two block
 * editor bundles (Field Blocks and Block Bindings), the Connection boxes,
 * SEO analysis (Yoast SEO, Rank Math), the live location rule check, and
 * the styles of the PHP screens (Settings, Go Pro).
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		builder: path.resolve( __dirname, 'assets/src/builder/index.js' ),
		fields: path.resolve( __dirname, 'assets/src/fields/index.js' ),
		map: path.resolve( __dirname, 'assets/src/map/index.js' ),
		blocks: path.resolve( __dirname, 'assets/src/blocks/index.js' ),
		bindings: path.resolve( __dirname, 'assets/src/bindings/index.js' ),
		connections: path.resolve( __dirname, 'assets/src/connections/index.js' ),
		seo: path.resolve( __dirname, 'assets/src/seo/index.js' ),
		'location-watch': path.resolve( __dirname, 'assets/src/location-watch/index.js' ),
		screens: path.resolve( __dirname, 'assets/src/screens/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
