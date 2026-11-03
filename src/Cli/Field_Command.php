<?php
/**
 * wp cfmb field
 *
 * @package CFMB
 */

namespace CFMB\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Read and change field values with the template API.
 *
 * Objects: a post ID, term_12, user_3, comment_5, option, option:{page} or widget:{id}.
 */
class Field_Command {

	/**
	 * Get a field value.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Field name.
	 *
	 * <object>
	 * : Post ID, term_12, user_3, comment_5, option, option:{page} or widget:{id}.
	 *
	 * [--raw]
	 * : The stored value instead of the formatted one.
	 *
	 * [--format=<format>]
	 * : json or plain.
	 * ---
	 * default: json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb field get price 42
	 *     wp cfmb field get phone option --format=plain
	 *
	 * @param string[]             $args       Name and object.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function get( $args, $assoc_args ): void {
		$value = cfmb_get_field( $args[0], self::object( $args[1] ), empty( $assoc_args['raw'] ) );

		if ( 'plain' === ( $assoc_args['format'] ?? 'json' ) && is_scalar( $value ) ) {
			\WP_CLI::line( (string) $value );
			return;
		}

		\WP_CLI::line( (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Save a field value. It is cleaned and checked like a form submission.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Field name.
	 *
	 * <value>
	 * : New value. With --json, a JSON value such as ["a","b"] or {"url":"…"}.
	 *
	 * <object>
	 * : Post ID, term_12, user_3, comment_5, option, option:{page} or widget:{id}.
	 *
	 * [--json]
	 * : Read the value as JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb field update price 19.90 42
	 *     wp cfmb field update tags '["a","b"]' 42 --json
	 *
	 * @param string[]             $args       Name, value and object.
	 * @param array<string, mixed> $assoc_args Options.
	 * @return void
	 */
	public function update( $args, $assoc_args ): void {
		$value = $args[1];

		if ( ! empty( $assoc_args['json'] ) ) {
			$value = json_decode( $value, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				\WP_CLI::error( 'The value is not valid JSON.' );
			}
		}

		if ( ! cfmb_update_field( $args[0], $value, self::object( $args[2] ) ) ) {
			\WP_CLI::error( 'Not saved. Check that the field is shown for that object and that the value is valid.' );
		}

		\WP_CLI::success( 'Saved.' );
	}

	/**
	 * Delete a field value. The field falls back to its default.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Field name.
	 *
	 * <object>
	 * : Post ID, term_12, user_3, comment_5, option, option:{page} or widget:{id}.
	 *
	 * @param string[] $args Name and object.
	 * @return void
	 */
	public function delete( $args ): void {
		if ( ! cfmb_delete_field( $args[0], self::object( $args[1] ) ) ) {
			\WP_CLI::error( 'Nothing deleted.' );
		}

		\WP_CLI::success( 'Deleted.' );
	}

	/**
	 * Object argument as the template API takes it. Bare numbers are post IDs.
	 *
	 * @param string $object Object argument.
	 * @return int|string
	 */
	private static function object( string $object ) {
		return ctype_digit( $object ) ? (int) $object : $object;
	}
}
