<?php
/**
 * Field-scoped filter helper.
 *
 * @package CFMB
 */

namespace CFMB\Core;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a field filter in three passes: general, per type, per name.
 *
 * `cfmb/field/load_value`, then `cfmb/field/load_value/type=text`,
 * then `cfmb/field/load_value/name=price`. The narrower filter runs last
 * so it wins.
 */
final class Hooks {

	/**
	 * Apply a field filter and its type= and name= variants.
	 *
	 * @param string $hook  Base hook name, e.g. cfmb/field/load_value.
	 * @param mixed  $value Value being filtered.
	 * @param Field  $field Field definition.
	 * @param mixed  ...$extra Extra arguments passed to callbacks after the field.
	 * @return mixed
	 */
	public static function filter_field( string $hook, $value, Field $field, ...$extra ) {
		$value = apply_filters( $hook, $value, $field, ...$extra ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash-separated hook names are part of the public API.
		$value = apply_filters( "{$hook}/type={$field->type()}", $value, $field, ...$extra ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// Sub-fields match by the name set in the builder (title), not the flattened key (items_0_title).
		if ( '' !== $field->base_name() ) {
			$value = apply_filters( "{$hook}/name={$field->base_name()}", $value, $field, ...$extra ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		}

		return $value;
	}
}
