<?php
/**
 * Contract for field types made of a few fixed parts.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Link and map. Each part is stored under its own key (cta_url, cta_title)
 * so meta_query can reach it, while templates get one array.
 */
interface Compound_Field extends Field_Type {

	/**
	 * Part names, used as key suffixes.
	 *
	 * @return string[]
	 */
	public function parts(): array;
}
