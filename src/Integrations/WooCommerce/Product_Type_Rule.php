<?php
/**
 * Product type location rule.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\WooCommerce;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches products by type: simple, variable, grouped, external, or a type
 * another plugin adds. Only registered while WooCommerce is active.
 */
class Product_Type_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'product_type';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Product type', 'custom-fields-meta-box' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'WooCommerce', 'custom-fields-meta-box' );
	}

	/**
	 * Product types WooCommerce knows.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return array();
		}

		return array_map( 'strval', (array) wc_get_product_types() );
	}

	/**
	 * Check the product's type. A new product counts as simple, like WooCommerce's default.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::POST !== $context->kind() || 'product' !== $context->get( 'post_type' ) ) {
			return null;
		}

		// The edit screen sends the type picked in the form before it is saved.
		$picked = $context->get( 'product_type' );
		if ( is_string( $picked ) && '' !== $picked ) {
			return $picked === $value;
		}

		$post_id = (int) $context->get( 'post_id', 0 );
		$terms   = $post_id ? wp_get_object_terms( $post_id, 'product_type', array( 'fields' => 'slugs' ) ) : array();
		$type    = is_array( $terms ) && $terms ? (string) $terms[0] : 'simple';

		return $type === $value;
	}
}
