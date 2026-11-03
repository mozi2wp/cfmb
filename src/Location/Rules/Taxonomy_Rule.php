<?php
/**
 * Taxonomy location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches term add and edit screens of a taxonomy.
 */
class Taxonomy_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'taxonomy';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Taxonomy', 'custom-fields-meta-box' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Taxonomy', 'custom-fields-meta-box' );
	}

	/**
	 * Taxonomies with an admin screen.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( self::ALL => __( 'All taxonomies', 'custom-fields-meta-box' ) );

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $name => $taxonomy ) {
			$choices[ $name ] = $taxonomy->labels->singular_name;
		}

		return $choices;
	}

	/**
	 * Check the taxonomy.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::TERM !== $context->kind() ) {
			return null;
		}

		return self::ALL === $value || $context->get( 'taxonomy' ) === $value;
	}
}
