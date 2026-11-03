<?php
/**
 * Polylang through its public functions.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the documented pll_* functions, available in Polylang and Polylang Pro.
 */
class Polylang_Plugin implements Language_Plugin {

	/**
	 * Whether Polylang is running.
	 *
	 * @return bool
	 */
	public function active(): bool {
		return function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' );
	}

	/**
	 * Default language.
	 *
	 * @return string
	 */
	public function default_language(): string {
		return function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';
	}

	/**
	 * Current language; false ("all languages" in the admin) counts as none.
	 *
	 * @return string
	 */
	public function current_language(): string {
		return function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
	}

	/**
	 * Language of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_language( int $post_id ): string {
		return function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post_id ) : '';
	}

	/**
	 * Language of a term.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function term_language( int $term_id ): string {
		return function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( $term_id ) : '';
	}

	/**
	 * Translated post. Attachments are only translated when media translation is on.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $language Language.
	 * @return int
	 */
	public function translated_post( int $post_id, string $language ): int {
		return function_exists( 'pll_get_post' ) ? (int) pll_get_post( $post_id, $language ) : 0;
	}

	/**
	 * Translated term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy. Unused: Polylang finds it from the term.
	 * @param string $language Language.
	 * @return int
	 */
	public function translated_term( int $term_id, string $taxonomy, string $language ): int {
		return function_exists( 'pll_get_term' ) ? (int) pll_get_term( $term_id, $language ) : 0;
	}
}
