<?php
/**
 * Post term location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Matches posts assigned a term. Value form: "taxonomy:slug".
 */
class Post_Term_Rule extends Abstract_Rule {

	/**
	 * Most terms listed per taxonomy in the builder.
	 */
	private const CHOICE_LIMIT = 300;

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'post_term';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Post term', 'custom-fields-meta-box' );
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
	 * Terms of every taxonomy with an admin screen.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array();

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
					'number'     => self::CHOICE_LIMIT,
				)
			);

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$choices[ $taxonomy->name . ':' . $term->slug ] = sprintf(
					/* translators: 1: taxonomy name, 2: term name */
					__( '%1$s: %2$s', 'custom-fields-meta-box' ),
					$taxonomy->labels->singular_name,
					$term->name
				);
			}
		}

		return $choices;
	}

	/**
	 * Check the post's terms.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Object_Ref::POST !== $context->kind() ) {
			return null;
		}

		$parts   = explode( ':', $value, 2 );
		$post_id = (int) $context->get( 'post_id', 0 );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		// The edit screen sends the terms ticked in the form, as slugs per taxonomy, before they are saved.
		$picked = $context->get( 'terms' );
		if ( is_array( $picked ) && array_key_exists( $parts[0], $picked ) ) {
			return in_array( $parts[1], array_map( 'strval', (array) $picked[ $parts[0] ] ), true );
		}

		if ( ! $post_id ) {
			return false;
		}

		return has_term( $parts[1], $parts[0], $post_id );
	}
}
