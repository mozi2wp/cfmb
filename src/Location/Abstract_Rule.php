<?php
/**
 * Shared behaviour for location rules.
 *
 * @package CFMB
 */

namespace CFMB\Location;

defined( 'ABSPATH' ) || exit;

/**
 * Handles "is" / "is not" and the "all" value. Subclasses answer one
 * question: does the context have this value, or is the rule not relevant here.
 */
abstract class Abstract_Rule implements Rule {

	public const ALL = 'all';

	/**
	 * "is" and "is not".
	 *
	 * @return array<string, string>
	 */
	public function operators(): array {
		return array(
			'==' => __( 'is', 'custom-fields-meta-box' ),
			'!=' => __( 'is not', 'custom-fields-meta-box' ),
		);
	}

	/**
	 * Match with operator handling. A rule that does not apply to the
	 * context never matches, whatever the operator; a "Post type is not page"
	 * rule should not put fields on user screens.
	 *
	 * @param Context $context  Screen or object.
	 * @param string  $operator Operator.
	 * @param string  $value    Rule value.
	 * @return bool
	 */
	public function match( Context $context, string $operator, string $value ): bool {
		$has = $this->context_has( $context, $value );

		if ( null === $has ) {
			return false;
		}

		return '!=' === $operator ? ! $has : $has;
	}

	/**
	 * Whether the context has the value; null when the rule does not apply.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	abstract protected function context_has( Context $context, string $value ): ?bool;
}
