<?php
/**
 * Navigation menu location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Matches a navigation menu: any menu, the menu in a theme location, or one
 * menu by slug. Slugs, not IDs, so rules survive Schema Sync between sites.
 */
class Menu_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'nav_menu';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Menu', 'custom-fields-meta-box' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Forms', 'custom-fields-meta-box' );
	}

	/**
	 * Any menu, theme locations and menus.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( self::ALL => __( 'Any menu', 'custom-fields-meta-box' ) );

		foreach ( get_registered_nav_menus() as $location => $description ) {
			$choices[ 'location/' . $location ] = sprintf(
				/* translators: %s: theme menu location, such as "Primary" */
				__( 'Menu in location: %s', 'custom-fields-meta-box' ),
				$description
			);
		}

		foreach ( wp_get_nav_menus() as $menu ) {
			$choices[ 'menu/' . $menu->slug ] = $menu->name;
		}

		return $choices;
	}

	/**
	 * Check the menu.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Context::MENU !== $context->kind() ) {
			return null;
		}

		return self::menu_matches( (int) $context->get( 'menu_id', 0 ), $value );
	}

	/**
	 * Whether a menu matches a rule value. Shared with the menu item rule.
	 *
	 * @param int    $menu_id Menu term ID.
	 * @param string $value   Rule value.
	 * @return bool
	 */
	public static function menu_matches( int $menu_id, string $value ): bool {
		if ( self::ALL === $value ) {
			return true;
		}

		if ( ! $menu_id ) {
			return false;
		}

		if ( 0 === strpos( $value, 'location/' ) ) {
			$locations = get_nav_menu_locations();

			return (int) ( $locations[ substr( $value, 9 ) ] ?? 0 ) === $menu_id;
		}

		if ( 0 === strpos( $value, 'menu/' ) ) {
			$menu = wp_get_nav_menu_object( $menu_id );

			return $menu instanceof \WP_Term && substr( $value, 5 ) === $menu->slug;
		}

		return false;
	}
}
