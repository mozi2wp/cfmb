<?php
/**
 * WPML through its public filter API.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Translation;

use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to WPML only through the documented `wpml_*` filters, so nothing
 * breaks when WPML's classes change.
 */
class Wpml_Plugin implements Language_Plugin {

	/**
	 * Whether WPML is running.
	 *
	 * @return bool
	 */
	public function active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) && '' !== $this->default_language();
	}

	/**
	 * Default language.
	 *
	 * @return string
	 */
	public function default_language(): string {
		return (string) apply_filters( 'wpml_default_language', '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * Current language; "all" counts as none.
	 *
	 * @return string
	 */
	public function current_language(): string {
		$language = (string) apply_filters( 'wpml_current_language', '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

		return 'all' === $language ? '' : $language;
	}

	/**
	 * Language of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_language( int $post_id ): string {
		$type = get_post_type( $post_id );

		if ( ! $type ) {
			return '';
		}

		return (string) apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
			'wpml_element_language_code',
			'',
			array(
				'element_id'   => $post_id,
				'element_type' => 'post_' . $type,
			)
		);
	}

	/**
	 * Language of a term. WPML keys term languages by term_taxonomy_id.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function term_language( int $term_id ): string {
		$term = get_term( $term_id );

		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		return (string) apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
			'wpml_element_language_code',
			'',
			array(
				'element_id'   => (int) $term->term_taxonomy_id,
				'element_type' => 'tax_' . $term->taxonomy,
			)
		);
	}

	/**
	 * Translated post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $language Language.
	 * @return int
	 */
	public function translated_post( int $post_id, string $language ): int {
		$type = get_post_type( $post_id );

		return $type ? (int) apply_filters( 'wpml_object_id', $post_id, $type, false, $language ) : 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}

	/**
	 * Translated term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param string $language Language.
	 * @return int
	 */
	public function translated_term( int $term_id, string $taxonomy, string $language ): int {
		return (int) apply_filters( 'wpml_object_id', $term_id, $taxonomy, false, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
	}
}
