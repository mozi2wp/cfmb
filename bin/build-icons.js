/**
 * Builds assets/icons/fontawesome.json from the Font Awesome Free package.
 *
 * Each entry is keyed "{style}-{name}" (solid-house, brands-github) and holds
 * a label and the SVG markup. Runs before `npm run build`; when the package
 * is not installed the existing file is kept.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const pkg = path.join( root, 'node_modules/@fortawesome/fontawesome-free' );
const source = path.join( pkg, 'metadata/icons.json' );
const target = path.join( root, 'assets/icons/fontawesome.json' );
const license = path.join( root, 'assets/icons/fontawesome-LICENSE.txt' );

if ( ! fs.existsSync( source ) ) {
	// eslint-disable-next-line no-console
	console.log( 'Font Awesome Free is not installed; keeping assets/icons/fontawesome.json as it is.' );
	process.exit( 0 );
}

const icons = JSON.parse( fs.readFileSync( source, 'utf8' ) );
const out = {};

Object.keys( icons )
	.sort()
	.forEach( ( name ) => {
		const icon = icons[ name ];
		const svgs = icon.svg || {};
		Object.keys( svgs )
			.sort()
			.forEach( ( style ) => {
				const svg = svgs[ style ];
				if ( ! svg || ! svg.viewBox || ! svg.path ) {
					return;
				}
				const paths = ( Array.isArray( svg.path ) ? svg.path : [ svg.path ] )
					.map( ( d ) => `<path d="${ d }"/>` )
					.join( '' );
				out[ `${ style }-${ name }` ] = {
					label: `${ icon.label || name } (${ style })`,
					svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${ svg.viewBox.join( ' ' ) }" fill="currentColor">${ paths }</svg>`,
				};
			} );
	} );

fs.writeFileSync( target, JSON.stringify( out ) );

const version = JSON.parse( fs.readFileSync( path.join( pkg, 'package.json' ), 'utf8' ) ).version;
const licenseText = fs.existsSync( path.join( pkg, 'LICENSE.txt' ) ) ? fs.readFileSync( path.join( pkg, 'LICENSE.txt' ), 'utf8' ) : '';
fs.writeFileSync( license, `Font Awesome Free ${ version } by @fontawesome - https://fontawesome.com\nIcons: CC BY 4.0 License (https://creativecommons.org/licenses/by/4.0/)\n\n${ licenseText }` );

// eslint-disable-next-line no-console
console.log( `Wrote ${ Object.keys( out ).length } Font Awesome icons (version ${ version }).` );
