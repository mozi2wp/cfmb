<?php
/**
 * Contract for icon libraries.
 *
 * @package CFMB
 */

namespace CFMB\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * A set of named icons the Icon field can pick from. Register more on
 * `cfmb/icons/register`, for example from an add-on with Font Awesome.
 */
interface Icon_Library {

	/**
	 * Library ID, stored as the value prefix: "dashicons" in dashicons:admin-home.
	 * Lowercase letters, digits, dashes and underscores.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Name shown in the picker.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Every icon, keyed by name. Each item: label, and either svg (markup) or
	 * class (CSS classes on an empty span).
	 *
	 * @return array<string, array<string, string>>
	 */
	public function icons(): array;

	/**
	 * Whether an icon exists.
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public function has( string $name ): bool;

	/**
	 * HTML for an icon. Decorative: hidden from screen readers.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public function render( string $name ): string;
}
