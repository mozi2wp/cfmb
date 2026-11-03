<?php
/**
 * PHPUnit bootstrap for the WordPress test suite (wp-env tests container).
 *
 * Run: npm run env run tests-cli --env-cwd=wp-content/plugins/cfmb vendor/bin/phpunit
 *
 * @package CFMB
 */

$cfmb_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : getenv( 'WP_PHPUNIT__DIR' );

if ( ! $cfmb_tests_dir || ! file_exists( $cfmb_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Set WP_TESTS_DIR to the WordPress test library. wp-env sets it in the tests container.\n" );
	exit( 1 );
}

require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $cfmb_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/cfmb.php';
	}
);

require $cfmb_tests_dir . '/includes/bootstrap.php';
