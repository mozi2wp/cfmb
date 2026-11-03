<?php
/**
 * Per-field options for translation, SEO and GraphQL plugins.
 *
 * @package CFMB
 */

namespace CFMB\Integrations;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults and cleaning for a field's `integrations` options. Only values
 * that differ from the default are stored, so groups saved before these
 * options existed keep the same JSON (and the same Schema Sync hash).
 */
final class Field_Integrations {

	public const TRANSLATE = 'translate';
	public const COPY      = 'copy';
	public const COPY_ONCE = 'copy_once';
	public const IGNORE    = 'ignore';

	/**
	 * Translation modes in the order the builder lists them.
	 */
	public const TRANSLATION_MODES = array( self::TRANSLATE, self::COPY, self::COPY_ONCE, self::IGNORE );

	/**
	 * Types whose value is words an editor writes, so each language gets its own.
	 */
	private const TRANSLATED_TYPES = array( 'text', 'textarea', 'wysiwyg', 'url', 'oembed', 'link' );

	/**
	 * Types SEO plugins read by default.
	 */
	private const SEO_TYPES = array( 'text', 'textarea', 'wysiwyg', 'image', 'gallery' );

	/**
	 * Translation mode a type gets when the field doesn't set one.
	 *
	 * @param string $type Field type key.
	 * @return string
	 */
	public static function default_translation( string $type ): string {
		return in_array( $type, self::TRANSLATED_TYPES, true ) ? self::TRANSLATE : self::COPY;
	}

	/**
	 * Whether SEO plugins read a type by default.
	 *
	 * @param string $type Field type key.
	 * @return bool
	 */
	public static function default_seo( string $type ): bool {
		return in_array( $type, self::SEO_TYPES, true );
	}

	/**
	 * Translation mode set on the field itself, or empty to follow the type
	 * (or the parent structure).
	 *
	 * @param Field $field Field.
	 * @return string
	 */
	public static function own_translation( Field $field ): string {
		$mode = (string) $field->integration( 'translation', '' );

		return in_array( $mode, self::TRANSLATION_MODES, true ) ? $mode : '';
	}

	/**
	 * Translation mode in effect. A mode set on a Group, Repeater or Stacks
	 * field applies to its sub-fields unless they set their own.
	 *
	 * @param Field  $field     Field.
	 * @param string $inherited Mode set on the closest parent structure, or empty.
	 * @return string
	 */
	public static function translation( Field $field, string $inherited = '' ): string {
		$own = self::own_translation( $field );

		if ( '' !== $own ) {
			return $own;
		}

		return '' !== $inherited ? $inherited : self::default_translation( $field->type() );
	}

	/**
	 * Whether SEO plugins should read the field.
	 *
	 * @param Field $field Field.
	 * @return bool
	 */
	public static function seo( Field $field ): bool {
		$value = $field->integration( 'seo', null );

		return null === $value ? self::default_seo( $field->type() ) : (bool) $value;
	}

	/**
	 * Whether the field shows in WPGraphQL when its group does.
	 *
	 * @param Field $field Field.
	 * @return bool
	 */
	public static function graphql( Field $field ): bool {
		return false !== $field->integration( 'graphql', true );
	}

	/**
	 * Clean raw builder input, keeping only values that differ from the defaults.
	 *
	 * @param mixed  $raw  Raw `integrations` value.
	 * @param string $type Field type key.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $raw, string $type ): array {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = array();

		$mode = (string) ( $raw['translation'] ?? '' );
		if ( in_array( $mode, self::TRANSLATION_MODES, true ) ) {
			$clean['translation'] = $mode;
		}

		if ( array_key_exists( 'seo', $raw ) && null !== $raw['seo'] && '' !== $raw['seo'] ) {
			$seo = (bool) $raw['seo'];

			if ( $seo !== self::default_seo( $type ) ) {
				$clean['seo'] = $seo;
			}
		}

		if ( array_key_exists( 'graphql', $raw ) && empty( $raw['graphql'] ) && null !== $raw['graphql'] ) {
			$clean['graphql'] = false;
		}

		return $clean;
	}
}
