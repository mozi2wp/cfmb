<?php
/**
 * Options page storage.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Stores options page values as `cfmb_{page}_{field}` and widget values as
 * `cfmb_widget_{widget id}_{field}` options, not autoloaded.
 *
 * On multilingual sites a field can keep one value per language
 * (`cfmb_{page}_{lang}_{field}`). The default language keeps the plain name,
 * and a language without its own value reads the default language's.
 */
class Option_Store implements Value_Store {

	/**
	 * Option name for a field on a page or widget.
	 *
	 * @param Object_Ref $ref      Options page or widget reference.
	 * @param string     $name     Field name.
	 * @param string     $language Language code for a per-language value, or empty.
	 * @return string
	 */
	public static function option_name( Object_Ref $ref, string $name, string $language = '' ): string {
		if ( Object_Ref::WIDGET === $ref->kind() ) {
			return 'cfmb_widget_' . $ref->page() . '_' . $name;
		}

		return 'cfmb_' . $ref->page() . '_' . ( '' !== $language ? $language . '_' : '' ) . $name;
	}

	/**
	 * Language a value is stored for, or empty for the shared value.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return string
	 */
	public static function language( Object_Ref $ref, string $name ): string {
		if ( Object_Ref::OPTION !== $ref->kind() ) {
			return '';
		}

		/**
		 * Filters the language an options page value is kept for. Empty
		 * means the value is shared by every language.
		 *
		 * @param string     $language Language code, empty by default.
		 * @param Object_Ref $ref      Options page.
		 * @param string     $name     Field name.
		 */
		return sanitize_key( (string) apply_filters( 'cfmb/storage/option_language', '', $ref, $name ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Name to read: the language's own option when it has one, else the shared one.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return string
	 */
	private function read_name( Object_Ref $ref, string $name ): string {
		$language = self::language( $ref, $name );

		if ( '' !== $language && $this->has_option( self::option_name( $ref, $name, $language ) ) ) {
			return self::option_name( $ref, $name, $language );
		}

		return self::option_name( $ref, $name );
	}

	/**
	 * Whether an option row exists.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private function has_option( string $option ): bool {
		$missing = new \stdClass();

		return get_option( $option, $missing ) !== $missing;
	}

	/**
	 * Whether a value was ever saved.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function exists( Object_Ref $ref, string $name ): bool {
		return $this->has_option( $this->read_name( $ref, $name ) );
	}

	/**
	 * Read a raw value.
	 *
	 * @param Object_Ref $ref      Object.
	 * @param string     $name     Field name.
	 * @param bool       $multiple Whether the value is a list.
	 * @return mixed
	 */
	public function get( Object_Ref $ref, string $name, bool $multiple ) {
		$value = get_option( $this->read_name( $ref, $name ), $multiple ? array() : '' );

		if ( $multiple ) {
			return is_array( $value ) ? array_values( $value ) : ( '' === $value ? array() : array( $value ) );
		}

		return $value;
	}

	/**
	 * Write a raw value.
	 *
	 * @param Object_Ref $ref      Object.
	 * @param string     $name     Field name.
	 * @param mixed      $value    Sanitized value.
	 * @param bool       $multiple Whether the value is a list.
	 * @return bool
	 */
	public function set( Object_Ref $ref, string $name, $value, bool $multiple ): bool {
		$option = self::option_name( $ref, $name, self::language( $ref, $name ) );

		update_option( $option, $multiple ? array_values( (array) $value ) : $value, false );

		return true;
	}

	/**
	 * Remove a value. In a non-default language only that language's copy goes.
	 *
	 * @param Object_Ref $ref  Object.
	 * @param string     $name Field name.
	 * @return bool
	 */
	public function delete( Object_Ref $ref, string $name ): bool {
		return delete_option( self::option_name( $ref, $name, self::language( $ref, $name ) ) );
	}
}
