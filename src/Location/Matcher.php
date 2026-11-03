<?php
/**
 * Decides which groups show where.
 *
 * @package CFMB
 */

namespace CFMB\Location;

use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates group location rules against a context.
 *
 * A group's location is a list of rule sets. The group shows when every
 * rule in at least one set matches (OR of ANDs).
 */
class Matcher {

	/**
	 * Group storage.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Location rules.
	 *
	 * @var Rule_Registry
	 */
	private Rule_Registry $rules;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups Group storage.
	 * @param Rule_Registry    $rules  Location rules.
	 */
	public function __construct( Group_Repository $groups, Rule_Registry $rules ) {
		$this->groups = $groups;
		$this->rules  = $rules;
	}

	/**
	 * Active groups that match a context, in display order.
	 *
	 * @param Context $context Screen or object.
	 * @return Group[]
	 */
	public function groups_for( Context $context ): array {
		return array_values(
			array_filter(
				$this->groups->active(),
				fn( Group $group ) => ! $group->preset_only() && $this->matches( $group, $context )
			)
		);
	}

	/**
	 * Find an active group by key, only if it matches the context.
	 *
	 * @param string  $key     Group key.
	 * @param Context $context Screen or object.
	 * @return Group|null
	 */
	public function matching_group( string $key, Context $context ): ?Group {
		foreach ( $this->groups_for( $context ) as $group ) {
			if ( $group->key() === $key ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Whether a group matches a context.
	 *
	 * @param Group   $group   Field Group.
	 * @param Context $context Screen or object.
	 * @return bool
	 */
	public function matches( Group $group, Context $context ): bool {
		// A group that defines a Field Block always belongs to that block.
		if ( Context::BLOCK === $context->kind() && '' !== $group->block_name() && $group->block_name() === $context->get( 'block' ) ) {
			return true;
		}

		foreach ( $group->location() as $rule_set ) {
			if ( $this->set_matches( (array) $rule_set, $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Active groups whose fields belong on a list screen: posts of a type,
	 * terms of a taxonomy, or users. A rule that depends on one object
	 * (template, status, terms, role) can't be checked for a whole list, so
	 * it counts as matching; the type itself must match.
	 *
	 * @param string $kind    post, term or user.
	 * @param string $subtype Post type or taxonomy; empty for users.
	 * @return Group[]
	 */
	public function groups_for_list( string $kind, string $subtype = '' ): array {
		$families = array(
			'post' => array( 'post_type', array( 'page_template', 'post_status', 'post_format', 'post_term', 'product_type' ) ),
			'term' => array( 'taxonomy', array() ),
			'user' => array( '', array( 'user_role', 'user_form' ) ),
		);

		if ( ! isset( $families[ $kind ] ) ) {
			return array();
		}

		list( $type_param, $loose ) = $families[ $kind ];

		$context = 'post' === $kind ? Context::for_post( 0, $subtype ) : ( 'term' === $kind ? Context::for_term( $subtype ) : Context::for_user( 0, 'edit' ) );

		return array_values(
			array_filter(
				$this->groups->active(),
				function ( Group $group ) use ( $type_param, $loose, $context ): bool {
					if ( $group->preset_only() ) {
						return false;
					}

					foreach ( $group->location() as $rule_set ) {
						if ( array() === (array) $rule_set ) {
							continue;
						}

						$typed = false;
						$ok    = true;

						foreach ( (array) $rule_set as $rule ) {
							$param = is_array( $rule ) ? (string) ( $rule['param'] ?? '' ) : '';

							if ( in_array( $param, $loose, true ) ) {
								// Users have no type rule; any user rule says the set is about users.
								$typed = $typed || '' === $type_param;
								continue;
							}

							$type = $this->rules->get( $param );

							if ( ! $type || ! $type->match( $context, (string) ( $rule['operator'] ?? '==' ), (string) ( $rule['value'] ?? '' ) ) ) {
								$ok = false;
								break;
							}

							$typed = $typed || $param === $type_param;
						}

						if ( $ok && $typed ) {
							return true;
						}
					}

					return false;
				}
			)
		);
	}

	/**
	 * Whether every rule in a set matches.
	 *
	 * @param array<int, mixed> $rule_set Rules.
	 * @param Context           $context  Screen or object.
	 * @return bool
	 */
	private function set_matches( array $rule_set, Context $context ): bool {
		if ( array() === $rule_set ) {
			return false;
		}

		foreach ( $rule_set as $rule ) {
			$param = is_array( $rule ) ? (string) ( $rule['param'] ?? '' ) : '';
			$type  = $this->rules->get( $param );

			if ( ! $type || ! $type->match( $context, (string) ( $rule['operator'] ?? '==' ), (string) ( $rule['value'] ?? '' ) ) ) {
				return false;
			}
		}

		return true;
	}
}
