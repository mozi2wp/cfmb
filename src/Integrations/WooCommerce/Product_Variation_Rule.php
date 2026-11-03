<?php
/**
 * Product variation location rule.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\WooCommerce;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a group inside each variation's settings on the product screen.
 * The value narrows it to variations of products in one category.
 */
class Product_Variation_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'product_variation';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Product variation', 'cfmb' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'WooCommerce', 'cfmb' );
	}

	/**
	 * Any variation, or variations of products in a category.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( self::ALL => __( 'Any variation', 'cfmb' ) );
		$terms   = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 300,
			)
		);

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			/* translators: %s: product category name */
			$choices[ 'product_cat:' . $term->slug ] = sprintf( __( 'Variations in "%s"', 'cfmb' ), $term->name );
		}

		return $choices;
	}

	/**
	 * Check the variation and, for category values, its parent product.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::POST !== $context->kind() || 'product_variation' !== $context->get( 'post_type' ) ) {
			return null;
		}

		if ( self::ALL === $value ) {
			return true;
		}

		if ( 0 !== strpos( $value, 'product_cat:' ) ) {
			return false;
		}

		$variation = get_post( (int) $context->get( 'post_id', 0 ) );
		$parent    = $variation instanceof WP_Post ? (int) $variation->post_parent : 0;

		// While assets load there is no variation yet; any category could apply.
		if ( ! $parent ) {
			return 0 === (int) $context->get( 'post_id', 0 );
		}

		return has_term( substr( $value, 12 ), 'product_cat', $parent );
	}
}
