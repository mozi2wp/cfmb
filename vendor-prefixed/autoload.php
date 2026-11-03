<?php
/**
 * Autoloader for prefixed third-party code.
 *
 * Mirrors what Strauss generates: classes under CFMB\Vendor\ load from this
 * folder. See composer.json "extra.strauss".
 *
 * @package CFMB
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefixes = array(
			'CFMB\\Vendor\\enshrined\\svgSanitize\\' => __DIR__ . '/enshrined/svg-sanitize/src/',
		);

		foreach ( $prefixes as $prefix => $dir ) {
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				continue;
			}

			$file = $dir . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

			if ( is_readable( $file ) ) {
				require $file;
			}

			return;
		}
	}
);
