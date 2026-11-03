<?php
/**
 * Finds field definitions for the template API.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves a field name on an object to its definition.
 *
 * Values carry no hidden reference row, so the definition is found by name.
 * When two groups use the same name, the group whose location matches the
 * object wins.
 */
class Field_Locator {

	/**
	 * Group storage.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups  Group storage.
	 * @param Matcher          $matcher Location matcher.
	 */
	public function __construct( Group_Repository $groups, Matcher $matcher ) {
		$this->groups  = $groups;
		$this->matcher = $matcher;
	}

	/**
	 * Find a field by name for an object.
	 *
	 * @param string     $name Field name.
	 * @param Object_Ref $ref  Object.
	 * @return array{0: Field, 1: Group}|null
	 */
	public function find( string $name, Object_Ref $ref ): ?array {
		$candidates = array();
		// A block only has the fields of its own groups; a post field of the same name is not its value.
		$groups = $ref->is_block() ? $this->groups_for( $ref ) : $this->groups->active();

		foreach ( $groups as $group ) {
			if ( $group->preset_only() ) {
				continue;
			}

			foreach ( $group->fields() as $field ) {
				if ( $field->name() === $name ) {
					$candidates[] = array( $field, $group );
				}
			}
		}

		if ( count( $candidates ) < 2 ) {
			return $candidates[0] ?? null;
		}

		$context = Context::from_ref( $ref );

		foreach ( $candidates as $candidate ) {
			if ( $this->matcher->matches( $candidate[1], $context ) ) {
				return $candidate;
			}
		}

		return $candidates[0];
	}

	/**
	 * Groups shown for an object.
	 *
	 * @param Object_Ref $ref Object.
	 * @return Group[]
	 */
	public function groups_for( Object_Ref $ref ): array {
		return $this->matcher->groups_for( Context::from_ref( $ref ) );
	}
}
