<?php
/**
 * Choice list parsing for select, checkbox, radio and button group.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Choices are stored as a list of { value, label } pairs so order is kept
 * and numeric values never get renumbered by PHP array handling.
 */
final class Choices {

	/**
	 * Normalize choices from a list of pairs or "value : Label" lines.
	 *
	 * @param mixed $raw Raw choices.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function sanitize( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = self::parse_lines( $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		$seen  = array();

		foreach ( $raw as $choice ) {
			if ( ! is_array( $choice ) || ! isset( $choice['value'] ) || ! is_scalar( $choice['value'] ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $choice['value'] );
			if ( '' === $value || isset( $seen[ $value ] ) ) {
				continue;
			}

			$label = isset( $choice['label'] ) && is_scalar( $choice['label'] ) ? sanitize_text_field( (string) $choice['label'] ) : '';

			$seen[ $value ] = true;
			$clean[]        = array(
				'value' => $value,
				'label' => '' === $label ? $value : $label,
			);
		}

		return $clean;
	}

	/**
	 * Parse "value : Label" lines. A line without a colon uses the text for both.
	 *
	 * @param string $text Lines of choices.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function parse_lines( string $text ): array {
		$choices = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $text ) ?: array() as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts     = array_map( 'trim', explode( ' : ', $line, 2 ) );
			$choices[] = array(
				'value' => $parts[0],
				'label' => $parts[1] ?? $parts[0],
			);
		}

		return $choices;
	}

	/**
	 * Values only.
	 *
	 * @param array<int, array{value: string, label: string}> $choices Choices.
	 * @return string[]
	 */
	public static function values( array $choices ): array {
		return array_column( $choices, 'value' );
	}

	/**
	 * Label for a value, or the value itself when it is not a known choice.
	 *
	 * @param array<int, array{value: string, label: string}> $choices Choices.
	 * @param string                                          $value   Value.
	 * @return string
	 */
	public static function label_for( array $choices, string $value ): string {
		foreach ( $choices as $choice ) {
			if ( $choice['value'] === $value ) {
				return $choice['label'];
			}
		}

		return $value;
	}
}
