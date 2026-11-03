<?php
/**
 * Options page location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Content\Options_Pages;
use CFMB\Core\Plugin;
use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches an options page.
 */
class Options_Page_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'options_page';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Options page', 'custom-fields-meta-box' );
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
	 * Registered options pages.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array();

		foreach ( Plugin::instance()->get( Options_Pages::class )->all() as $id => $page ) {
			$choices[ $id ] = $page['page_title'];
		}

		return $choices;
	}

	/**
	 * Check the page.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::OPTION !== $context->kind() ) {
			return null;
		}

		return $context->get( 'page' ) === $value;
	}
}
