<?php
/**
 * User role location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches users with a role. On the add-user form only "All roles" matches,
 * since the user has no role yet.
 */
class User_Role_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'user_role';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'User role', 'cfmb' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'User', 'cfmb' );
	}

	/**
	 * Every role.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( self::ALL => __( 'All roles', 'cfmb' ) );

		foreach ( wp_roles()->get_names() as $role => $name ) {
			$choices[ $role ] = translate_user_role( $name );
		}

		return $choices;
	}

	/**
	 * Check the user's roles.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::USER !== $context->kind() ) {
			return null;
		}

		if ( self::ALL === $value ) {
			return true;
		}

		$user = get_userdata( (int) $context->get( 'user_id', 0 ) );

		return $user ? in_array( $value, (array) $user->roles, true ) : false;
	}
}
