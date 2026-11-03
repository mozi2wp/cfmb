<?php
/**
 * What CFMB needs from a multilingual plugin.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Translation;

defined( 'ABSPATH' ) || exit;

/**
 * Implemented once for WPML and once for Polylang.
 */
interface Language_Plugin {

	/**
	 * Whether the plugin is active and ready.
	 *
	 * @return bool
	 */
	public function active(): bool;

	/**
	 * Default language code, e.g. "en".
	 *
	 * @return string
	 */
	public function default_language(): string;

	/**
	 * Language being viewed or edited, or empty for "all languages".
	 *
	 * @return string
	 */
	public function current_language(): string;

	/**
	 * Language of a post, or empty when unknown.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_language( int $post_id ): string;

	/**
	 * Language of a term, or empty when unknown.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function term_language( int $term_id ): string;

	/**
	 * The post's translation in a language, or 0 when there is none.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $language Language code.
	 * @return int
	 */
	public function translated_post( int $post_id, string $language ): int;

	/**
	 * The term's translation in a language, or 0 when there is none.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param string $language Language code.
	 * @return int
	 */
	public function translated_term( int $term_id, string $taxonomy, string $language ): int;
}
