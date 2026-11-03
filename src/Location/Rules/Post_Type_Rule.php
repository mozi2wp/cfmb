<?php
/**
 * Post type location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches posts of a type.
 */
class Post_Type_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'post_type';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Post type', 'cfmb' );
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
	 * Post types with an admin screen.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array();

		foreach ( self::editable_post_types() as $name => $type ) {
			$choices[ $name ] = $type->labels->singular_name;
		}

		return $choices;
	}

	/**
	 * Post types fields can be added to. Internal block editor types are left out.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	public static function editable_post_types(): array {
		$types = get_post_types( array( 'show_ui' => true ), 'objects' );

		return array_filter(
			$types,
			static fn( $type ) => 'attachment' !== $type->name
				&& Group_Repository::POST_TYPE !== $type->name
				&& 0 !== strpos( $type->name, 'wp_' )
		);
	}

	/**
	 * Check the post type.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::POST !== $context->kind() ) {
			return null;
		}

		return self::ALL === $value || $context->get( 'post_type' ) === $value;
	}
}
