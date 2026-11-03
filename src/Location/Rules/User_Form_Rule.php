<?php
/**
 * User form location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches the add-user form, the profile/edit-user form, or both.
 */
class User_Form_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'user_form';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'User form', 'cfmb' );
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
	 * Forms.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		return array(
			self::ALL => __( 'Add and edit', 'cfmb' ),
			'add'     => __( 'Add new user', 'cfmb' ),
			'edit'    => __( 'Profile and edit user', 'cfmb' ),
		);
	}

	/**
	 * Check the form.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::USER !== $context->kind() ) {
			return null;
		}

		return self::ALL === $value || $context->get( 'user_form' ) === $value;
	}
}
