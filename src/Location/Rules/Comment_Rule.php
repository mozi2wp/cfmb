<?php
/**
 * Comment location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Matches comments, all of them or those on a post type.
 */
class Comment_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'comment';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Comment on', 'custom-fields-meta-box' );
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
	 * Post types that take comments.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( self::ALL => __( 'Any content', 'custom-fields-meta-box' ) );

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $type ) {
			if ( post_type_supports( $name, 'comments' ) ) {
				$choices[ $name ] = $type->labels->singular_name;
			}
		}

		return $choices;
	}

	/**
	 * Check the post type the comment belongs to.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Context::COMMENT !== $context->kind() ) {
			return null;
		}

		return self::ALL === $value || $context->get( 'post_type' ) === $value;
	}
}
