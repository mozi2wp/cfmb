<?php
/**
 * Widget location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Matches classic widgets, all of them or one widget type. Works on the
 * classic widgets screen and in the Legacy Widget block.
 */
class Widget_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'widget';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Widget', 'cfmb' );
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
	 * Registered widget types.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		global $wp_widget_factory;

		$choices = array( self::ALL => __( 'All widgets', 'cfmb' ) );

		if ( $wp_widget_factory instanceof \WP_Widget_Factory ) {
			foreach ( $wp_widget_factory->widgets as $widget ) {
				if ( $widget instanceof \WP_Widget ) {
					$choices[ $widget->id_base ] = $widget->name;
				}
			}
		}

		return $choices;
	}

	/**
	 * Check the widget type.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Context::WIDGET !== $context->kind() ) {
			return null;
		}

		return self::ALL === $value || $context->get( 'id_base' ) === $value;
	}
}
