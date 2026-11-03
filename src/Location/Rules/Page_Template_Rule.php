<?php
/**
 * Page template location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches posts using a template. Checked when the screen loads; switch
 * the template and save to see fields for the new one.
 */
class Page_Template_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'page_template';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Template', 'cfmb' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Post', 'cfmb' );
	}

	/**
	 * Default template plus every theme template for any post type.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( 'default' => __( 'Default template', 'cfmb' ) );
		$theme   = wp_get_theme();

		foreach ( array_keys( Post_Type_Rule::editable_post_types() ) as $post_type ) {
			foreach ( $theme->get_page_templates( null, $post_type ) as $file => $name ) {
				$choices[ $file ] = $name;
			}
		}

		return $choices;
	}

	/**
	 * Check the assigned template.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::POST !== $context->kind() ) {
			return null;
		}

		$post_id  = (int) $context->get( 'post_id', 0 );
		$picked   = $context->get( 'template' );
		// The edit screen sends the template picked in the form before it is saved.
		$template = is_string( $picked ) ? $picked : ( $post_id ? (string) get_page_template_slug( $post_id ) : '' );

		return ( '' === $template ? 'default' : $template ) === $value;
	}
}
