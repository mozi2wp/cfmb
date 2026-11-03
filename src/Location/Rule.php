<?php
/**
 * Contract for location rules.
 *
 * @package CFMB
 */

namespace CFMB\Location;

defined( 'ABSPATH' ) || exit;

/**
 * One kind of location rule, such as "Post type" or "User role".
 */
interface Rule {

	/**
	 * Rule key stored in group locations, e.g. "post_type".
	 *
	 * @return string
	 */
	public function param(): string;

	/**
	 * Label shown in the builder.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Builder category label, e.g. "Post".
	 *
	 * @return string
	 */
	public function category(): string;

	/**
	 * Operators this rule supports, operator => label.
	 *
	 * @return array<string, string>
	 */
	public function operators(): array;

	/**
	 * Values to pick from, value => label. Loaded on demand by the builder.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array;

	/**
	 * Whether the rule matches a context.
	 *
	 * @param Context $context  Screen or object.
	 * @param string  $operator Operator.
	 * @param string  $value    Rule value.
	 * @return bool
	 */
	public function match( Context $context, string $operator, string $value ): bool;
}
