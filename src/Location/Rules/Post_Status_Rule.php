<?php
/**
 * Post status location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches posts by status. A new post counts as a draft.
 */
class Post_Status_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'post_status';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Post status', 'custom-fields-meta-box' );
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
	 * Statuses shown in admin lists.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array();

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $name => $status ) {
			$choices[ $name ] = (string) $status->label;
		}

		return $choices;
	}

	/**
	 * Check the status.
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
		$picked  = $context->get( 'status' );
		$status  = is_string( $picked ) && '' !== $picked ? $picked : ( $post_id ? (string) get_post_status( $post_id ) : 'draft' );

		return ( 'auto-draft' === $status ? 'draft' : $status ) === $value;
	}
}
