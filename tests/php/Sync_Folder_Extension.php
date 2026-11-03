<?php
/**
 * Keeps Schema Sync files and cached definitions from one test out of the next.
 *
 * Saving a group writes a JSON file, and files are not rolled back with the
 * database. Without this, a group saved in one test is still active (as a
 * file-only group) in every test after it.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Sync\Sync_Files;
use PHPUnit\Runner\AfterTestHook;

/**
 * Empties the test sync folder after every test.
 */
final class Sync_Folder_Extension implements AfterTestHook {

	private const REPOSITORIES = array(
		\CFMB\Schema\Group_Repository::class,
		\CFMB\Content\Post_Type_Repository::class,
		\CFMB\Content\Taxonomy_Repository::class,
		\CFMB\Content\Options_Page_Repository::class,
		\CFMB\Connections\Connection_Repository::class,
	);

	/**
	 * Folder the tests write sync files to.
	 *
	 * @return string
	 */
	public static function folder(): string {
		return sys_get_temp_dir() . '/cfmb-tests-json-' . getmypid();
	}

	/**
	 * Remove the files a test wrote.
	 *
	 * @param string $test Test name.
	 * @param float  $time Seconds the test took.
	 * @return void
	 */
	public function executeAfterTest( string $test, float $time ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		foreach ( (array) glob( self::folder() . '/*.json' ) as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		if ( ! function_exists( 'cfmb' ) ) {
			return;
		}

		cfmb()->get( Sync_Files::class )->reset();

		// The database rolls back without firing save_post, so request caches would keep rows that are gone.
		foreach ( self::REPOSITORIES as $repository ) {
			cfmb()->get( $repository )->flush();
		}
	}
}
