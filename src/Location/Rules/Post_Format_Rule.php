<?php
/**
 * Post format location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches posts by format.
 */
class Post_Format_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'post_format';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Post format', 'custom-fields-meta-box' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Post', 'custom-fields-meta-box' );
	}

	/**
	 * Every post format.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		return get_post_format_strings();
	}

	/**
	 * Check the format.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::POST !== $context->kind() ) {
			return null;
		}

		$post_id = (int) $context->get( 'post_id', 0 );
		$picked  = $context->get( 'format' );
		$format  = is_string( $picked ) ? $picked : ( $post_id ? get_post_format( $post_id ) : false );

		return ( $format ? $format : 'standard' ) === $value;
	}
}
