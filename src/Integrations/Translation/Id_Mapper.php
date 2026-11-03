<?php
/**
 * Points copied IDs at the translated objects.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Translation;

use CFMB\Schema\Field;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * When a value is copied to a translation, an image, linked post or term
 * should be its translation in the target language. Objects without a
 * translation keep the original ID, so nothing is lost.
 */
class Id_Mapper {

	/**
	 * Types whose stored values are post IDs (attachments included).
	 */
	private const POST_TYPES = array( 'image', 'file', 'gallery', 'post', 'page_link', 'relationship' );

	/**
	 * Whether a field stores object IDs that can be mapped.
	 *
	 * @param Field $field Field.
	 * @return bool
	 */
	public static function maps( Field $field ): bool {
		return in_array( $field->type(), self::POST_TYPES, true ) || 'taxonomy' === $field->type();
	}

	/**
	 * Map a stored value: one ID, a list of IDs or a comma-separated string.
	 * Anything else is returned as it was.
	 *
	 * @param Field           $field    Field.
	 * @param mixed           $value    Stored value.
	 * @param string          $language Target language.
	 * @param Language_Plugin $plugin   Multilingual plugin.
	 * @return mixed
	 */
	public function map( Field $field, $value, string $language, Language_Plugin $plugin ) {
		if ( '' === $language || ! self::maps( $field ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_map( fn( $item ) => $this->map( $field, $item, $language, $plugin ), $value );
		}

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$mapped = $this->map_id( $field, (int) $value, $language, $plugin );

			return is_int( $value ) ? $mapped : (string) $mapped;
		}

		if ( is_string( $value ) && 1 === preg_match( '/^\d+(,\d+)+$/', $value ) ) {
			$ids = array_map( fn( $id ) => $this->map_id( $field, (int) $id, $language, $plugin ), explode( ',', $value ) );

			return implode( ',', $ids );
		}

		return $value;
	}

	/**
	 * Map one ID.
	 *
	 * @param Field           $field    Field.
	 * @param int             $id       Object ID.
	 * @param string          $language Target language.
	 * @param Language_Plugin $plugin   Multilingual plugin.
	 * @return int
	 */
	private function map_id( Field $field, int $id, string $language, Language_Plugin $plugin ): int {
		if ( $id <= 0 ) {
			return $id;
		}

		if ( 'taxonomy' === $field->type() ) {
			$taxonomy = (string) $field->setting( 'taxonomy', '' );

			if ( '' === $taxonomy ) {
				$term     = get_term( $id );
				$taxonomy = $term instanceof WP_Term ? $term->taxonomy : '';
			}

			$mapped = '' !== $taxonomy ? $plugin->translated_term( $id, $taxonomy, $language ) : 0;
		} else {
			$mapped = $plugin->translated_post( $id, $language );
		}

		/**
		 * Filters an ID copied to a translation.
		 *
		 * @param int    $mapped   Translated ID, or the original when there is no translation.
		 * @param int    $id       Original ID.
		 * @param Field  $field    Field.
		 * @param string $language Target language.
		 */
		return (int) apply_filters( 'cfmb/translation/map_id', $mapped > 0 ? $mapped : $id, $id, $field, $language ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}
}
