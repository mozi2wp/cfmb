<?php
/**
 * Keeps Taxonomy fields and post terms in step.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

use CFMB\Schema\Field;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy fields can assign their terms to the post ("Also set the post's
 * terms") and show the post's current terms ("Show the post's current terms").
 * Runs on the public value filters, like an add-on would.
 */
class Term_Sync {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'cfmb/field/update_value/type=taxonomy', array( $this, 'save_terms' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		add_filter( 'cfmb/field/load_value/type=taxonomy', array( $this, 'load_terms' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Assign the chosen terms to the post.
	 *
	 * @param mixed      $value Sanitized term IDs.
	 * @param Field      $field Field definition.
	 * @param Object_Ref $ref   Object.
	 * @return mixed
	 */
	public function save_terms( $value, $field, $ref ) {
		if ( ! $this->applies( $field, $ref, 'save_terms' ) ) {
			return $value;
		}

		$taxonomy = (string) $field->setting( 'taxonomy', 'category' );
		$ids      = array_map( 'intval', array_filter( (array) $value ) );

		if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( (string) get_post_type( $ref->id() ), $taxonomy ) ) {
			wp_set_object_terms( $ref->id(), $ids, $taxonomy );
		}

		return $value;
	}

	/**
	 * Read the post's terms instead of the stored value.
	 *
	 * @param mixed      $value Raw value.
	 * @param Field      $field Field definition.
	 * @param Object_Ref $ref   Object.
	 * @return mixed
	 */
	public function load_terms( $value, $field, $ref ) {
		if ( ! $this->applies( $field, $ref, 'load_terms' ) ) {
			return $value;
		}

		$terms = wp_get_object_terms( $ref->id(), (string) $field->setting( 'taxonomy', 'category' ), array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) ) {
			return $value;
		}

		$ids = array_map( 'intval', $terms );

		if ( in_array( $field->setting( 'appearance', 'checkbox' ), array( 'checkbox', 'multi_select' ), true ) ) {
			return $ids;
		}

		return $ids ? $ids[0] : '';
	}

	/**
	 * Whether the setting is on and the object is a post.
	 *
	 * @param mixed  $field   Field definition.
	 * @param mixed  $ref     Object.
	 * @param string $setting save_terms or load_terms.
	 * @return bool
	 */
	private function applies( $field, $ref, string $setting ): bool {
		return $field instanceof Field
			&& $ref instanceof Object_Ref
			&& Object_Ref::POST === $ref->kind()
			&& $ref->id() > 0
			&& (bool) $field->setting( $setting, false );
	}
}
