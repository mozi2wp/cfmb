<?php
/**
 * Menu item location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Matches items of a navigation menu, by the menu they belong to.
 */
class Menu_Item_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'nav_menu_item';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Menu item', 'cfmb' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Forms', 'cfmb' );
	}

	/**
	 * Same choices as the menu rule: the item's menu is what is checked.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = ( new Menu_Rule() )->choices();

		$choices[ self::ALL ] = __( 'Items of any menu', 'cfmb' );

		return $choices;
	}

	/**
	 * Check the item's menu.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Context::MENU_ITEM !== $context->kind() ) {
			return null;
		}

		return Menu_Rule::menu_matches( (int) $context->get( 'menu_id', 0 ), $value );
	}
}
