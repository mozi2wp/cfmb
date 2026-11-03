<?php
/**
 * Field conditional logic.
 *
 * @package CFMB
 */

namespace CFMB\Location;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a field is shown, based on sibling field values.
 *
 * Mirrors assets/src/shared/conditions.js; both must give the same answer
 * so a field hidden in the browser is also treated as hidden on save.
 */
final class Conditions {

	/**
	 * Supported operators.
	 *
	 * @return string[]
	 */
	public static function operators(): array {
		return array( '==', '!=', 'empty', 'not_empty', 'contains', '>', '<' );
	}

	/**
	 * Whether a field is visible. Fields without conditions always are.
	 *
	 * @param Field                $field  Field to check.
	 * @param array<string, mixed> $values Sibling values keyed by field key.
	 * @return bool
	 */
	public static function visible( Field $field, array $values ): bool {
		$sets = $field->conditions();

		if ( array() === $sets ) {
			return true;
		}

		foreach ( $sets as $rules ) {
			if ( self::set_passes( (array) $rules, $values ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether every rule in a set passes.
	 *
	 * @param array<int, mixed>    $rules  Rules.
	 * @param array<string, mixed> $values Values by field key.
	 * @return bool
	 */
	private static function set_passes( array $rules, array $values ): bool {
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				return false;
			}

			$actual = $values[ (string) ( $rule['field'] ?? '' ) ] ?? null;

			if ( ! self::compare( $actual, (string) ( $rule['operator'] ?? '==' ), (string) ( $rule['value'] ?? '' ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Compare one value.
	 *
	 * @param mixed  $actual   Current value of the watched field.
	 * @param string $operator Operator.
	 * @param string $expected Value in the rule.
	 * @return bool
	 */
	public static function compare( $actual, string $operator, string $expected ): bool {
		$is_empty = null === $actual || '' === $actual || array() === $actual;

		switch ( $operator ) {
			case 'empty':
				return $is_empty;

			case 'not_empty':
				return ! $is_empty;

			case '!=':
				return ! self::equals( $actual, $expected );

			case 'contains':
				if ( is_array( $actual ) ) {
					return in_array( $expected, array_map( 'strval', $actual ), true );
				}

				return '' !== $expected && is_scalar( $actual ) && false !== strpos( (string) $actual, $expected );

			case '>':
			case '<':
				if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
					return false;
				}

				return '>' === $operator ? $actual + 0 > $expected + 0 : $actual + 0 < $expected + 0;

			default:
				return self::equals( $actual, $expected );
		}
	}

	/**
	 * Equality. For lists, "equals" means the list includes the value.
	 *
	 * @param mixed  $actual   Value.
	 * @param string $expected Expected value.
	 * @return bool
	 */
	private static function equals( $actual, string $expected ): bool {
		if ( is_array( $actual ) ) {
			return in_array( $expected, array_map( 'strval', $actual ), true );
		}

		if ( is_bool( $actual ) ) {
			$actual = $actual ? '1' : '0';
		}

		return is_scalar( $actual ) && (string) $actual === $expected;
	}
}
