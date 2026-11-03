<?php
/**
 * Stable key generation for groups and fields.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Creates random, stable keys. Keys never change once assigned, which is
 * what lets a field be renamed without losing track of it.
 */
final class Key_Generator {

	public const FIELD_PREFIX  = 'fld_';
	public const GROUP_PREFIX  = 'grp_';
	public const LAYOUT_PREFIX = 'lay_';

	public const POST_TYPE_PREFIX    = 'cpt_';
	public const TAXONOMY_PREFIX     = 'tax_';
	public const OPTIONS_PAGE_PREFIX = 'opt_';
	public const CONNECTION_PREFIX   = 'con_';

	/**
	 * New field key, e.g. fld_9f2c41ab07.
	 *
	 * @return string
	 */
	public static function field(): string {
		return self::FIELD_PREFIX . self::random();
	}

	/**
	 * New group key, e.g. grp_5d0e7a19c3.
	 *
	 * @return string
	 */
	public static function group(): string {
		return self::GROUP_PREFIX . self::random();
	}

	/**
	 * New Stacks layout key, e.g. lay_3b8e10c4d2.
	 *
	 * @return string
	 */
	public static function layout(): string {
		return self::LAYOUT_PREFIX . self::random();
	}

	/**
	 * New key with any of the prefixes above.
	 *
	 * @param string $prefix Key prefix.
	 * @return string
	 */
	public static function make( string $prefix ): string {
		return $prefix . self::random();
	}

	/**
	 * Whether a string is a well-formed key with the given prefix.
	 *
	 * @param string $key    Candidate key.
	 * @param string $prefix One of the prefix constants.
	 * @return bool
	 */
	public static function is_valid( string $key, string $prefix ): bool {
		return 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '[a-z0-9]{6,32}$/', $key );
	}

	/**
	 * Ten lowercase hex characters.
	 *
	 * @return string
	 */
	private static function random(): string {
		return bin2hex( random_bytes( 5 ) );
	}
}
