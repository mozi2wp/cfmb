<?php
/**
 * PHPUnit bootstrap for the WordPress test suite (wp-env tests container).
 *
 * Run: npm run test:php (wp-env), or composer test with WP_PHPUNIT__TESTS_CONFIG set.
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

require_once __DIR__ . '/Sync_Folder_Extension.php';

// Schema Sync files go to a temporary folder, emptied after every test.
if ( ! is_dir( CFMB\Tests\Sync_Folder_Extension::folder() ) ) {
	mkdir( CFMB\Tests\Sync_Folder_Extension::folder(), 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
}
tests_add_filter( 'cfmb/sync/save_path', array( CFMB\Tests\Sync_Folder_Extension::class, 'folder' ) );
tests_add_filter(
	'cfmb/sync/load_paths',
	static function () {
		return array( CFMB\Tests\Sync_Folder_Extension::folder() );
	}
);

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/custom-fields-meta-box.php';
	}
);

require $cfmb_tests_dir . '/includes/bootstrap.php';
