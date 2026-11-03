<?php
/**
 * PHP code for definitions.
 *
 * @package CFMB
 */

namespace CFMB\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Writes definitions as cfmb_register_*() calls for a theme or plugin.
 * Definitions registered in code show in the builder as read-only.
 */
final class Php_Exporter {

	/**
	 * Registration function per kind.
	 */
	private const FUNCTIONS = array(
		'group'        => 'cfmb_register_group',
		'post_type'    => 'cfmb_register_post_type',
		'taxonomy'     => 'cfmb_register_taxonomy',
		'options_page' => 'cfmb_register_options_page',
		'connection'   => 'cfmb_register_connection',
	);

	/**
	 * Code for several definitions, hooked to cfmb/register.
	 *
	 * @param array<int, array{kind: string, item: array<string, mixed>}> $definitions Portable definitions with their kind.
	 * @return string PHP code without the opening tag.
	 */
	public static function export( array $definitions ): string {
		$calls = array();

		foreach ( $definitions as $definition ) {
			$function = self::FUNCTIONS[ $definition['kind'] ] ?? '';

			if ( '' !== $function ) {
				$calls[] = "\t" . $function . '( ' . self::value( $definition['item'], 1 ) . ' );';
			}
		}

		return "add_action(\n\t'cfmb/register',\n\tfunction () {\n" . implode( "\n\n", array_map( static fn( $call ) => "\t" . str_replace( "\n", "\n\t", $call ), $calls ) ) . "\n\t}\n);\n";
	}

	/**
	 * PHP literal for a value, in WordPress style: array( 'key' => value ).
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Indent depth.
	 * @return string
	 */
	private static function value( $value, int $depth ): string {
		if ( is_array( $value ) ) {
			if ( array() === $value ) {
				return 'array()';
			}

			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$indent  = str_repeat( "\t", $depth + 1 );
			$lines   = array();

			foreach ( $value as $key => $item ) {
				$lines[] = $indent . ( $is_list ? '' : var_export( $key, true ) . ' => ' ) . self::value( $item, $depth + 1 ) . ','; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Builds code to copy, not debug output.
			}

			return "array(\n" . implode( "\n", $lines ) . "\n" . str_repeat( "\t", $depth ) . ')';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return 'null';
		}

		return var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Builds code to copy, not debug output.
	}
}
