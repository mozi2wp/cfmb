<?php
/**
 * Carries validation errors from a save to the next page load.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Edit screens save with a normal form POST followed by a redirect, so
 * errors are parked in a short-lived transient for the current user.
 */
final class Save_Errors {

	/**
	 * How long errors are kept.
	 */
	private const TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Errors already taken this request, so every group on the screen sees them.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $taken = array();

	/**
	 * Remember errors for an object.
	 *
	 * @param Object_Ref            $ref    Object.
	 * @param array<string, string> $errors Messages keyed by field key.
	 * @return void
	 */
	public static function remember( Object_Ref $ref, array $errors ): void {
		if ( $errors ) {
			set_transient( self::key( $ref ), $errors, self::TTL );
		}
	}

	/**
	 * Get and forget errors for an object.
	 *
	 * @param Object_Ref $ref Object.
	 * @return array<string, string>
	 */
	public static function take( Object_Ref $ref ): array {
		$key = self::key( $ref );

		if ( isset( self::$taken[ $key ] ) ) {
			return self::$taken[ $key ];
		}

		$errors = get_transient( $key );

		if ( false !== $errors ) {
			delete_transient( $key );
		}

		self::$taken[ $key ] = is_array( $errors ) ? $errors : array();

		return self::$taken[ $key ];
	}

	/**
	 * Transient name.
	 *
	 * @param Object_Ref $ref Object.
	 * @return string
	 */
	private static function key( Object_Ref $ref ): string {
		return 'cfmb_errors_' . md5( get_current_user_id() . '|' . $ref );
	}
}
